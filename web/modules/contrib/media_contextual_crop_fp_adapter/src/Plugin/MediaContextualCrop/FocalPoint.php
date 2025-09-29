<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop_fp_adapter\Plugin\MediaContextualCrop;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\focal_point\FocalPointManager;
use Drupal\media_contextual_crop\MediaContextualCropPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the media_contextual_crop.
 *
 * @MediaContextualCrop(
 *   id = "focal_point",
 *   target_field_name = "focal_point",
 *   label = @Translation("Focal Point"),
 *   description = @Translation("Manage Media Contextual Crop for Focal Point Crop."),
 *   image_style_effect = {"focal_point_scale_and_crop", "focal_point", "focal_point_crop"}
 * )
 */
class FocalPoint extends MediaContextualCropPluginBase {

  /**
   * FocalPoint Manager.
   *
   * @var Drupal\focal_point\FocalPointManager
   */
  protected $focalPointManager;

  /**
   * Focal Point Crop Data.
   *
   * @var array
   */
  protected $cropType;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                                    $plugin_id,
                                    $plugin_definition,
                              EntityTypeManagerInterface $entityTypeManager,
                              FocalPointManager $focalPointManager,
                                ConfigFactory $config
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityTypeManager);
    $this->focalPointManager = $focalPointManager;
    $this->cropType = $config->get('focal_point.settings')->get('crop_type');

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
      $container->get('focal_point.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function saveCrop($crop_settings, string $image_style_name, string $old_uri, string $context, int $width, int $height) {

    // Recover Crop.
    $crop = $this->retrieveContextualCrop($context, $this->cropType, $old_uri);

    // Recalculate coordinate.
    [$x, $y] = explode(',', $crop_settings);
    $absolute = $this->focalPointManager->relativeToAbsolute((float) $x, (float) $y, $width, $height);

    // Set anchor.
    $anchor = $crop->anchor();
    if ($anchor['x'] != $absolute['x'] || $anchor['y'] != $absolute['y']) {
      $crop->setPosition($absolute['x'], $absolute['y']);
      $crop->save();
    }

    return $crop->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getComponentConfig($default_values, $image_style_crops, $preview_image_style = NULL) {
    $component_data = [
      'type' => 'image_focal_point',
      'settings' => [
        'progess_indicator' => 'throbber',
        'preview_image_style' => $preview_image_style ?? 'crop_thumbnail',
        'preview_link' => TRUE,
        'offsets' => $default_values['data-crop-settings'] ?? '30,30',
      ],
    ];

    return $component_data;
  }

  /**
   * {@inheritdoc}
   */
  public function finishElement(&$form, $source_field_name, $default_values) {
    parent::finishElement($form, $source_field_name, $default_values);

    $form[$source_field_name]['#parents'] = [];
    $form[$source_field_name]['#weight'] = -10;
    $widget = &$form[$source_field_name]['widget'][0];
    if (array_key_exists('data-crop-settings', $default_values)) {
      $widget['#default_value']['focal_point'] = $default_values['data-crop-settings'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function widgetSave(array $form, FormStateInterface $form_state) {
    parent::widgetSave($form, $form_state);

    $focal_point = $form_state->getValue(['field_media_image', 0, 'focal_point']);
    if ($focal_point) {
      $form_state->setValue(['attributes', 'data-crop-settings'], $focal_point);
    }

    $form_state->setValue(['attributes', 'data-crop-type'], 'focal_point');
  }

  /**
   * {@inheritdoc}
   */
  public static function widgetModify(array $element): array {

    $element = parent::widgetModify($element);

    // Since core does not support nested modal dialogs, we need to ensure that
    // the preview page opens in a new tab, rather than a modal dialog via AJAX.
    $preview_link_attributes = &$element['preview']['preview_link']['#attributes'];
    unset($preview_link_attributes['data-dialog-type']);
    $preview_link_attributes['class'] = array_diff($preview_link_attributes['class'], ['use-ajax']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function processEmbedData($embed_settings) {

    // Model the attributes after the multi_crop focal_point plugin.
    $crop_settings = [
      'plugin_id' => 'focal_point',
      'crop_setting' => $embed_settings['crop'],
      'context' => $embed_settings['context'],
      'base_crop_folder' => 'multi_crop_embed',
    ];

    return $crop_settings;

  }

}
