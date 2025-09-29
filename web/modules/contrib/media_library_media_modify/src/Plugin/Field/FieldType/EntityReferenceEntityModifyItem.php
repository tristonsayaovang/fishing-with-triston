<?php

namespace Drupal\media_library_media_modify\Plugin\Field\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Plugin implementation of the 'entity_reference_entity_modify' field type.
 */
#[FieldType(
  id: "entity_reference_entity_modify",
  label: new TranslatableMarkup("Media with contextual modifications"),
  description: new TranslatableMarkup("An media field containing a media reference and additional data."),
  category: "reference",
  default_widget: "media_library_media_modify_widget",
  default_formatter: "entity_reference_entity_view",
  list_class: EntityReferenceFieldItemList::class
)]
class EntityReferenceEntityModifyItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return [
      'target_type' => 'media',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $element = [];
    // This can only be used for media fields.
    $element['target_type'] = [
      '#type' => 'hidden',
      '#value' => 'media',
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['overwritten_property_map'] = DataDefinition::create('string')
      ->setLabel(t('Overwritten property map'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema = parent::schema($field_definition);

    $schema['columns']['overwritten_property_map'] = [
      'description' => 'A map to overwrite entity data per instance.',
      'type' => 'text',
      'size' => 'big',
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    if ($name == 'entity' && !empty(parent::__get('entity'))) {

      $map = Json::decode($this->values['overwritten_property_map'] ?? '{}');

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = clone parent::__get('entity');
      if ($entity->hasTranslation($this->getLangcode())) {
        $translation = $entity->getTranslation($this->getLangcode());
        $this->overwriteFields($translation, $map);
      }
      else {
        $this->overwriteFields($entity, $map);
      }
      return $entity;
    }
    return parent::__get($name);
  }

  /**
   * Override entity fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to override.
   * @param array $overwritten_property_map
   *   The new values.
   */
  protected function overwriteFields(FieldableEntityInterface $entity, array $overwritten_property_map): void {
    foreach ($overwritten_property_map as $field_name => $field_value) {
      $values = $field_value;
      if (is_array($field_value) && !empty($field_value)) {
        // Remove keys that don't exist in original entity.
        $original_value = $entity->get($field_name)->getValue();
        if ($original_value) {
          $field_value = array_intersect_key($field_value, $original_value);
          $values = NestedArray::mergeDeepArray([
            $entity->get($field_name)->getValue(),
            $field_value,
          ], TRUE);
        }
      }
      $entity->set($field_name, $values);
    }
    if ($overwritten_property_map) {
      $entity->addCacheableDependency($this->getEntity());
      $entity->entity_reference_entity_modify = sprintf('%s:%s:%s.%s', $this->getEntity()->getEntityTypeId(), $this->getEntity()->bundle(), $this->getEntity()->id(), $this->getPropertyPath());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions(): array {
    return [];
  }

}
