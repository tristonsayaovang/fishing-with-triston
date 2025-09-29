<?php

namespace Drupal\entity_reference_entity_modify\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\media_library_media_modify\EntityReferenceOverrideService;

/**
 * Implementation of the 'entity_reference_autocomplete_with_override' widget.
 */
#[FieldWidget(
  id: 'entity_reference_autocomplete_with_override',
  label: new TranslatableMarkup("Autocomplete (with override"),
  description: new TranslatableMarkup("An autocomplete text field with overrides"),
  field_types: ["entity_reference_entity_modify"],
)]
class EntityReferenceAutocompleteWithOverrideWidget extends EntityReferenceAutocompleteWidget {

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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Form mode: @form_mode', ['@form_mode' => $this->getSetting('form_mode')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = parent::settingsForm($form, $form_state);
    $settings['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#default_value' => $this->getSetting('form_mode'),
      '#description' => $this->t('The override form mode for referenced entities.'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions($this->fieldDefinition->getSetting('target_type')),
    ];
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    return $this->entityReferenceOverrideService->formElement($items, $delta, $element, $form, $form_state, $this->getSetting('form_mode'));
  }

}
