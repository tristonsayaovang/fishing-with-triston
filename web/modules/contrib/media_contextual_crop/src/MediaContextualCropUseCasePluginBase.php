<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for media_contextual_crop_use_case plugins.
 */
abstract class MediaContextualCropUseCasePluginBase extends PluginBase implements MediaContextualCropUseCaseInterface, ContainerFactoryPluginInterface {

  /**
   * Media_contextual_crop Plugin Manager.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropPluginManager
   */
  protected $mccPluginManager;

  /**
   * Media_contextual_crop Main service.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropService
   */
  protected $mccService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MediaContextualCropPluginManager $mccPluginManager,
    MediaContextualCropService $mccService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mccService = $mccService;
    $this->mccPluginManager = $mccPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.media_contextual_crop'),
      $container->get('media_contextual_crop.service')
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
  public function isCompetent(ImageItem $item) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextualizedImage($crop_settings, $old_uri, $image_style) {
    $crop_settings['image_style'] = $image_style;

    // If there is contextual modification.
    $item_values = $crop_settings['item_values'];
    $old_image = [
      '#uri' => $old_uri,
      '#width' => $item_values['width'],
      '#height' => $item_values['height'],
    ];

    // Prepare Crop & generate new image path.
    $new_uri = $this->mccService->generateContextualizedImage($old_image, $crop_settings);

    return $new_uri;
  }

}
