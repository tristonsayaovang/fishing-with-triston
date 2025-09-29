<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for media_contextual_crop plugins.
 */
abstract class MediaContextualCropPluginBase extends PluginBase implements MediaContextualCropInterface, ContainerFactoryPluginInterface {

  /**
   * Media_contextual_crop Plugin Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFieldName() {
    return (string) $this->pluginDefinition['target_field_name'];
  }

  /**
   * Create or load crop instance.
   *
   * @param string $context
   *   Context of the crop.
   * @param string $crop_type
   *   Crop Type id.
   * @param string $original_uri
   *   Uri of original image.
   *
   * @return \Drupal\crop\Entity\Crop
   *   Return the crop entity.
   */
  public function retrieveContextualCrop($context, $crop_type, $original_uri) {

    $cropStorage = $this->entityTypeManager->getStorage('crop');

    // Try to load crop for the current context.
    $base_crop = ['uri' => $original_uri, 'type' => $crop_type, 'context' => $context];
    $crop = $cropStorage->loadByProperties($base_crop);

    $crop = reset($crop) ?: NULL;

    // Create a new crop.
    if ($crop == NULL) {

      /** @var \Drupal\file\FileInterface[] $files */
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uri' => $original_uri]);

      /** @var \Drupal\file\FileInterface|null $file */
      $file = reset($files) ?: NULL;

      $values = [
        'type' => $crop_type,
        'entity_id' => $file->id(),
        'entity_type' => 'file',
        'uri' => $original_uri,
        'context' => $context,
      ];

      // Create new cron.
      $crop = $cropStorage->create($values);
    }

    return $crop;

  }

  /**
   * {@inheritdoc}
   */
  public static function processFieldData($field_data) {
    return $field_data;
  }

  /**
   * Prepare widget/component settings.
   *
   * @param array $default_values
   *   Form Default values.
   * @param array $image_style_crops
   *   Crop configuration in the image style.
   *
   * @return array
   *   Component settings.
   */
  public function getComponentConfig(array $default_values, array $image_style_crops) {
    return [];
  }

  /**
   * Finish form element.
   *
   * @param array $form
   *   The form.
   * @param string $source_field_name
   *   Source FieldName.
   * @param array $default_values
   *   Default values for the widget.
   */
  public function finishElement(array &$form, string $source_field_name, array $default_values) {
    $form['actions']['save_modal']['#submit'][] = [$this, 'widgetSave'];

    $form[$source_field_name]['#parents'] = [];
    $form[$source_field_name]['#weight'] = -10;

    $widget = &$form[$source_field_name]['widget'][0];
    $widget['#alt_field'] = FALSE;
    $widget['#alt_field_required'] = FALSE;
    $widget['#process'][] = [$this, 'widgetModify'];
  }

  /**
   * Hides upload, remove button, and file link from file widget.
   *
   * @param array $element
   *   The form element to process.
   *
   * @return array
   *   The processed form element.
   *
   * @noinspection PhpUnused
   */
  public static function widgetModify(array $element): array {
    $remove_access = [
      'upload_button',
      'remove_button',
    ];

    // Remove the file link.
    foreach ($element['#files'] as $file) {
      $remove_access[] = 'file_' . $file->id();
    }

    foreach ($remove_access as $key) {
      $element[$key]['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * Save the form value as a data attribute on the media embed element.
   *
   * @param array $form
   *   Element Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function widgetSave(array $form, FormStateInterface $form_state) {
  }

}
