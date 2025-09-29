<?php

namespace Drupal\media_library_media_modify\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;
use Drupal\Component\Utility\NestedArray;
use Drupal\media_library\Ajax\UpdateSelectionCommand;
use Drupal\media_library\MediaLibraryState;
use Drupal\media_library_media_modify\MediaLibraryMediaModifyUiBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\media_library_media_modify\Plugin\Field\FieldType\EntityReferenceEntityModifyItem;
use Drupal\media_library_media_modify\EntityReferenceOverrideService;
use Drupal\Core\Render\Element;
use Drupal\Component\Utility\SortArray;

/**
 * Plugin implementation of the 'media_library_media_modify_widget' widget.
 */
#[FieldWidget(
  id: 'media_library_media_modify_widget',
  label: new TranslatableMarkup("Media library extra"),
  description: new TranslatableMarkup("Allows you to select items from the media library and modify them in context."),
  field_types: ["entity_reference", "entity_reference_entity_modify"],
  multiple_values: TRUE
)]
class MediaLibraryWithOverrideWidget extends MediaLibraryWidget {

  /**
   * The entity display repository service.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The entity reference entity modify service.
   *
   * @var \Drupal\media_library_media_modify\EntityReferenceOverrideService
   */
  protected $entityReferenceOverrideService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setEntityReferenceOverrideService($container->get('media_library_media_modify'));
    $widget->setEntityDisplayRepository($container->get('entity_display.repository'));
    return $widget;
  }

  /**
   * Set the entity reference entity modify service.
   *
   * @param \Drupal\media_library_media_modify\EntityReferenceOverrideService $entityModifyWidgetService
   *   The entity reference entity modify service.
   */
  protected function setEntityReferenceOverrideService(EntityReferenceOverrideService $entityModifyWidgetService): void {
    $this->entityReferenceOverrideService = $entityModifyWidgetService;
  }

  /**
   * Set the entity display repository service.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   */
  protected function setEntityDisplayRepository(EntityDisplayRepositoryInterface $entityDisplayRepository): void {
    $this->entityDisplayRepository = $entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings() + [
      'form_mode' => 'default',
      'check_selected' => FALSE,
      'multi_edit_on_create' => FALSE,
      'no_edit_on_create' => FALSE,
      'replace_checkbox_by_order_indicator' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Multi edit form on create: @multi_edit_on_create', ['@multi_edit_on_create' => $this->getSetting('multi_edit_on_create') ? $this->t('true') : $this->t('false')]);
    $summary[] = $this->t('No edit form on create: @no_edit_on_create', ['@no_edit_on_create' => $this->getSetting('no_edit_on_create') ? $this->t('true') : $this->t('false')]);
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1) {
      $summary[] = $this->t('Check selected: @check_selected', ['@check_selected' => $this->getSetting('check_selected') ? $this->t('true') : $this->t('false')]);
      $summary[] = $this->t('Replace checkbox by order indicator: @replace_checkbox_by_order_indicator', ['@replace_checkbox_by_order_indicator' => $this->getSetting('replace_checkbox_by_order_indicator') ? $this->t('true') : $this->t('false')]);
    }
    if (is_a($this->fieldDefinition->getItemDefinition()->getClass(), EntityReferenceEntityModifyItem::class, TRUE)) {
      $summary[] = $this->t('Form mode: @form_mode', ['@form_mode' => $this->getSetting('form_mode')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = parent::settingsForm($form, $form_state);

    $settings['multi_edit_on_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Multi edit on create'),
      '#default_value' => $this->getSetting('multi_edit_on_create'),
      '#description' => $this->t('Show a multi edit form after creating a new media item.'),
      '#states' => [
        'disabled' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][no_edit_on_create]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $settings['no_edit_on_create'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('No edit on create'),
      '#default_value' => $this->getSetting('no_edit_on_create'),
      '#description' => $this->t("Don't show an edit form after creating a new media item."),
      '#states' => [
        'disabled' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][multi_edit_on_create]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1) {
      $settings['check_selected'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Check selected'),
        '#default_value' => $this->getSetting('check_selected'),
        '#description' => $this->t('Checks the items of the field widget in the media library.'),
      ];
      $settings['replace_checkbox_by_order_indicator'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Replace checkbox by order indicator'),
        '#default_value' => $this->getSetting('replace_checkbox_by_order_indicator'),
        '#description' => $this->t('The checkbox in the media library will be replaced by an order indicator.'),
      ];
    }
    if (is_a($this->fieldDefinition->getItemDefinition()->getClass(), EntityReferenceEntityModifyItem::class, TRUE)) {
      $settings['form_mode'] = [
        '#type' => 'select',
        '#title' => $this->t('Form mode'),
        '#default_value' => $this->getSetting('form_mode'),
        '#description' => $this->t('The override form mode for referenced entities.'),
        '#options' => $this->entityDisplayRepository->getFormModeOptions($this->fieldDefinition->getSetting('target_type')),
      ];
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    /** @var \Drupal\media_library\MediaLibraryState $state */
    $state = $element['open_button']['#media_library_state'];

    $opener_parameters = $state->getOpenerParameters();
    // Convert bool settings into strings to match the hash after the state was
    // re-created from the request.
    $opener_parameters += [
      'replace_checkbox_by_order_indicator' => strval(intval($this->getSetting('replace_checkbox_by_order_indicator'))),
      'multi_edit_on_create' => strval(intval($this->getSetting('multi_edit_on_create'))),
      'no_edit_on_create' => strval(intval($this->getSetting('no_edit_on_create'))),
      'check_selected' => strval(intval($this->getSetting('check_selected'))),
    ];

    if (!$items->isEmpty() && $this->getSetting('check_selected')) {
      $opener_parameters['selected_ids'] = array_unique(array_column($items->getValue(), 'target_id'));
      $state->set('media_library_remaining', $element['#cardinality']);
    }
    $state->set('media_library_opener_parameters', $opener_parameters);

    // Recalculate the hash.
    $state->set('hash', $state->getHash());

    // If the field can store contextual overrides, add an edit button to each item to open the override form.
    // Otherwise, add a button to open the global media edit form.
    if (is_a($this->fieldDefinition->getItemDefinition()->getClass(), EntityReferenceEntityModifyItem::class, TRUE)) {
      foreach (Element::children($element['selection']) as $delta) {
        $element['selection'][$delta] += $this->entityReferenceOverrideService->formElement($items, $delta, [], $form, $form_state, $this->getSetting('form_mode'));
        $element['selection'][$delta]['edit']['#attributes'] = [
          'class' => [
            'media-library-item__edit',
            'icon-link',
          ],
        ];
      }
    }
    elseif (is_a($this->fieldDefinition->getItemDefinition()->getClass(), EntityReferenceItem::class, TRUE)) {
      $dialog_options = MediaLibraryMediaModifyUiBuilder::dialogOptions();
      foreach (Element::children($element['selection']) as $delta) {
        $element['selection'][$delta]['edit'] = [
          '#type' => 'link',
          '#title' => $this->t('Edit media item'),
          '#url' => $items->get($delta)->entity->toUrl('edit-form'),
          '#access' => $items->get($delta)->entity->access('update'),
          '#attributes' => [
            'class' => [
              'media-library-item__edit',
              'icon-link',
              'use-ajax',
            ],
            'data-dialog-options' => json_encode([
              'height' => $dialog_options['height'],
              'width' => $dialog_options['width'],
            ], JSON_THROW_ON_ERROR),
            'data-dialog-type' => 'modal',
          ],
          '#attached' => [
            'library' => [
              'core/drupal.dialog.ajax',
            ],
          ],
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function openMediaLibrary(array $form, FormStateInterface $form_state, ?Request $request = NULL): AjaxResponse {
    $response = parent::openMediaLibrary($form, $form_state);

    $state = MediaLibraryState::fromRequest($request);
    if ($selected_ids = $state->getOpenerParameters()['selected_ids'] ?? []) {
      $response->addCommand(new UpdateSelectionCommand($selected_ids), TRUE);
    }

    $settings = $state->getOpenerParameters();
    $response->addAttachments([
      'library' => ['media_library_media_modify/order_indicator'],
      'drupalSettings' => [
        'media_library_media_modify' => [
          'replace_checkbox_by_order_indicator' => (bool) $settings['replace_checkbox_by_order_indicator'],
        ],
      ],
    ]);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public static function addItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    /** @var \Drupal\media_library\MediaLibraryState $state */
    $state = $element['open_button']['#media_library_state'];
    if (!$state->getOpenerParameters()['check_selected']) {
      parent::addItems($form, $form_state);
      return;
    }

    $media = static::getNewMediaItems($element, $form_state);
    if (!empty($media)) {
      $field_state = static::getFieldState($element, $form_state);
      $target_ids = array_column($field_state['items'], 'target_id');

      $weight = 0;
      foreach ($media as $media_item) {
        if (in_array($media_item->id(), $target_ids, TRUE)) {
          $index = array_search($media_item->id(), $target_ids, TRUE);
          $field_state['items'][$index]['_weight'] = $weight++;
        }
        else {
          // Any ID can be passed to the widget, so we have to check access.
          if ($media_item->access('view')) {
            $field_state['items'][] = [
              'target_id' => $media_item->id(),
              '_weight' => $weight++,
            ];
          }
        }
      }
      // Remove unselected items.
      foreach ($target_ids as $target_id) {
        if (!isset($media[$target_id])) {
          $index = array_search($target_id, $target_ids, TRUE);
          unset($field_state['items'][$index]);
        }
      }
      usort($field_state['items'], function ($a, $b) {
        return SortArray::sortByKeyInt($a, $b, '_weight');
      });
      unset($field_state['original_deltas']);
      $field_state['items_count'] = count($field_state['items']);
      NestedArray::setValue($form_state->getUserInput(), $element['selection']['#parents'], $field_state['items']);
      static::setFieldState($element, $form_state, $field_state);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public static function validateItems(array $form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -1));

    /** @var \Drupal\media_library\MediaLibraryState $state */
    $state = $element['open_button']['#media_library_state'];
    if (!$state->getOpenerParameters()['check_selected']) {
      parent::validateItems($form, $form_state);
      return;
    }

    // For the check_selected feature, we need to empty the item count
    // temporarily.
    $field_state = static::getFieldState($element, $form_state);
    $field_state['items_count'] = 0;
    static::setFieldState($element, $form_state, $field_state);

    parent::validateItems($form, $form_state);

    $field_state['items_count'] = count($field_state['items']);
    static::setFieldState($element, $form_state, $field_state);
  }

}
