<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * MediaContextualCropUseCase plugin manager.
 */
class MediaContextualCropUseCasePluginManager extends DefaultPluginManager {

  /**
   * The main Media Contextual crop service.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropService
   */
  private $mccService;

  /**
   * Constructs MediaContextualCropUseCasePluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\media_contextual_crop\MediaContextualCropService $mccService
   *   The main Media Contextual crop service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, MediaContextualCropService $mccService) {
    parent::__construct(
      'Plugin/MediaContextualCropUseCase',
      $namespaces,
      $module_handler,
      'Drupal\media_contextual_crop\MediaContextualCropUseCaseInterface',
      'Drupal\media_contextual_crop\Annotation\MediaContextualCropUseCase'
    );
    $this->mccService = $mccService;
    $this->alterInfo('media_contextual_crop_use_case_info');
    $this->setCacheBackend($cache_backend, 'media_contextual_crop_use_case_plugins');
  }

  /**
   * Find if a use Case plugin are available to process this image.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   Image item.
   *
   * @return null|object
   *   Return competent plugin or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function findCompetent(ImageItem $item) {

    foreach ($this->getDefinitions() as $plugin_def) {
      $plugin = $this->createInstance($plugin_def['id']);
      if ($plugin->isCompetent($item)) {
        return $plugin;
      }
    }

    return NULL;
  }

}
