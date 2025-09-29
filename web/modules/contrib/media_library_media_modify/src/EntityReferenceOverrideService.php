<?php

namespace Drupal\media_library_media_modify;

use Drupal\Component\Utility\DiffArray;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library_media_modify\Form\ModifyEntityForm;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;

/**
 * Service for re-usable functions.
 */
class EntityReferenceOverrideService {

  use StringTranslationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key value service.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValue;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity last installed schema repository service.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The widget plugin manager.
   *
   * @var \Drupal\Core\Field\WidgetPluginManager
   */
  protected $widgetPluginManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue
   *   The key value service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository
   *   The entity last installed schema repository service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   * @param \Drupal\Core\Field\WidgetPluginManager $widgetPluginManager
   *   The widget plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $configFactory, KeyValueFactoryInterface $keyValue, EntityFieldManagerInterface $entityFieldManager, EntityLastInstalledSchemaRepositoryInterface $entityLastInstalledSchemaRepository, EntityDisplayRepositoryInterface $entityDisplayRepository, WidgetPluginManager $widgetPluginManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->configFactory = $configFactory;
    $this->keyValue = $keyValue;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityLastInstalledSchemaRepository = $entityLastInstalledSchemaRepository;
    $this->entityDisplayRepository = $entityDisplayRepository;
    $this->widgetPluginManager = $widgetPluginManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Migrates an entity_reference field to entity_reference_entity_modify field.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $field_name
   *   The field name.
   */
  public function migrateEntityReferenceField(string $entity_type_id, string $field_name): void {
    $field_storage_config = $this->configFactory->getEditable("field.storage.$entity_type_id.$field_name");

    if ($field_storage_config->get('type') !== 'entity_reference') {
      throw new \Exception('Not an entity reference field');
    }

    /* @see \Drupal\media_library_media_modify\Plugin\Field\FieldType\EntityReferenceEntityModifyItem::schema() */
    $schema_spec = [
      'description' => 'A map to overwrite entity data per instance.',
      'type' => 'text',
      'size' => 'big',
    ];

    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);

    $this->database->schema()->addField($entity_type_id . '__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    if ($entity_type_definition->isRevisionable()) {
      $this->database->schema()->addField($entity_type_id . '_revision__' . $field_name, $field_name . '_overwritten_property_map', $schema_spec);
    }

    $store = $this->keyValue->get("entity.storage_schema.sql");
    $data = $store->get("$entity_type_id.field_schema_data.$field_name");
    $data["{$entity_type_id}__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    if ($entity_type_definition->isRevisionable()) {
      $data["{$entity_type_id}_revision__$field_name"]['fields']["{$field_name}_overwritten_property_map"] = $schema_spec;
    }
    $store->set("$entity_type_id.field_schema_data.$field_name", $data);

    /** @var \Drupal\field\Entity\FieldStorageConfig[] $schema_definitions */
    $schema_definitions = $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $schema_definitions[$field_name]->set('type', 'entity_reference_entity_modify');
    $this->entityLastInstalledSchemaRepository->setLastInstalledFieldStorageDefinitions($entity_type_id, $schema_definitions);

    $this->entityFieldManager->clearCachedFieldDefinitions();

    $field_storage_config->set('type', 'entity_reference_entity_modify');
    $field_storage_config->save(TRUE);

    FieldStorageConfig::loadByName($entity_type_id, $field_name)->calculateDependencies()->save();

    // Use the default widget and settings.
    $component = $this->widgetPluginManager->prepareConfiguration('entity_reference_entity_modify', []);

    $field_map = $this->entityFieldManager->getFieldMapByFieldType('entity_reference')[$entity_type_id][$field_name];
    foreach ($field_map['bundles'] as $bundle) {
      $field_config = $this->configFactory->getEditable("field.field.$entity_type_id.$bundle.$field_name");
      $field_config->set('field_type', 'entity_reference_entity_modify');
      $field_config->save();

      /** @var \Drupal\field\FieldConfigInterface $field_config */
      $field_config = FieldConfig::loadByName($entity_type_id, $bundle, $field_name);
      $field_config->calculateDependencies()->save();

      $form_modes = $this->entityDisplayRepository->getFormModeOptionsByBundle($entity_type_id, $bundle);
      foreach (array_keys($form_modes) as $form_mode) {
        $form_display = $this->entityDisplayRepository->getFormDisplay($entity_type_id, $bundle, $form_mode);
        if ($form_display->getComponent($field_name)) {
          $form_display->setComponent($field_name, $component);
          $form_display->save();
        }
      }
    }
  }

  /**
   * Returns the difference of given fields in two entities.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity
   *   The referenced entity.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $original_entity
   *   The original entity.
   * @param array $fields
   *   The fields to compare.
   *
   * @return array
   *   The difference of the fields.
   */
  public function getOverriddenValues(FieldableEntityInterface $referenced_entity, FieldableEntityInterface $original_entity, array $fields): array {
    $values = [];
    foreach ($fields as $field_name) {
      $original_field = $original_entity->get($field_name);

      // Merge in not defined keys of original field.
      $referenced_entity->set($field_name, NestedArray::mergeDeepArray([
        $original_field->getValue(),
        $referenced_entity->get($field_name)->getValue(),
      ], TRUE));

      if (!$referenced_entity->get($field_name)->equals($original_field)) {
        /** @var \Drupal\Core\Field\FieldItemList $item_list */
        $item_list = $referenced_entity->get($field_name);
        /** @var \ArrayIterator $iterator */
        $iterator = $item_list->getIterator();
        // Filter out values that won't be saved.
        $referenced_entity_values = array_map(fn($item) => $item->toArray(), $iterator->getArrayCopy());
        $values[$field_name] = DiffArray::diffAssocRecursive($referenced_entity_values, $original_field->getValue());
      }
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, int $delta, array $element, array &$form, FormStateInterface $form_state, string $form_mode = 'default'): array {
    $entity = $items->getEntity();
    $field_name = $items->getFieldDefinition()->getName();

    if (empty($items->referencedEntities()[$delta])) {
      return $element;
    }

    $parents = $form['#parents'];
    // Create an ID suffix from the parents to make sure each widget is unique.
    $id_suffix = $parents ? '-' . implode('-', $parents) : '';

    $field_state = MediaLibraryWidget::getWidgetState($parents, $field_name, $form_state);
    $original_delta = $field_state['original_deltas'][$delta] ?? $delta;

    $field_widget_id = implode(':', array_filter([
      $field_name . '-' . $original_delta,
      $id_suffix,
    ]));

    $element['overwritten_property_map'] = [
      '#type' => 'hidden',
      '#default_value' => $items->get($delta)->overwritten_property_map ?? '{}',
      '#attributes' => [
        'data-entity-reference-override-value' => $field_widget_id,
      ],
    ];

    $element['edit'] = [
      '#type' => 'button',
      '#name' => $field_name . '-' . $original_delta . '-entity-reference-override-edit-button' . $id_suffix,
      '#value' => sprintf('Override %s in context of this %s',
        $items->get($delta)->entity->getEntityType()->getSingularLabel(),
        $entity->getEntityType()->getSingularLabel()),
      '#ajax' => [
        'callback' => [static::class, 'openOverrideForm'],
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Opening override form.'),
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
      // Allow the override modal to be opened and saved even if there are form
      // errors for other fields.
      '#limit_validation_errors' => [array_merge($parents, [$field_name])],
      '#media_library_media_modify' => [
        'referenced_entity_field' => $items->get($delta),
        'form_mode' => $form_mode,
        'field_widget_id' => $field_widget_id,
      ],
    ];

    return $element;
  }

  /**
   * Opens the override form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response object.
   */
  public static function openOverrideForm(array $form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();

    /** @var \Drupal\Core\Field\FieldItemListInterface $referenced_entity_field */
    $referenced_entity_field = $button['#media_library_media_modify']['referenced_entity_field'];

    /** @var \Drupal\Core\Entity\ContentEntityInterface $referenced_entity */
    $referenced_entity = $referenced_entity_field->entity;
    if ($referenced_entity->hasTranslation($referenced_entity_field->getLangcode())) {
      $referenced_entity = $referenced_entity->getTranslation($referenced_entity_field->getLangcode());
    }

    $override_form = \Drupal::formBuilder()->getForm(ModifyEntityForm::class, [
      'referenced_entity' => $referenced_entity,
      'referencing_entity_type_id' => $referenced_entity_field->getEntity()->getEntityTypeId(),
      'form_mode' => $button['#media_library_media_modify']['form_mode'],
      'field_widget_id' => $button['#media_library_media_modify']['field_widget_id'],
    ]);
    $dialog_options = [
      'minHeight' => '75%',
      'maxHeight' => '75%',
      'width' => '75%',
    ];

    $modal_title = t('Override %entity_type in context of %bundle "%label"', [
      '%entity_type' => $referenced_entity->getEntityType()->getSingularLabel(),
      '%bundle' => ucfirst($referenced_entity_field->getEntity()->bundle()),
      '%label' => $referenced_entity_field->getEntity()->label(),
    ]);

    if (ModifyEntityForm::access(\Drupal::currentUser())->isForbidden()) {
      return (new AjaxResponse())
        ->addCommand(new MessageCommand(t("You don't have access to set overrides for this item."), NULL, ['type' => 'warning']));
    }

    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($modal_title, $override_form, $dialog_options));
  }

}
