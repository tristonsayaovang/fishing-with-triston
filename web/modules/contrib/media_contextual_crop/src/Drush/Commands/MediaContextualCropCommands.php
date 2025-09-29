<?php

namespace Drupal\media_contextual_crop\Drush\Commands;

use Drupal\Core\Config\CachedStorage;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\media_contextual_crop\MediaContextualCropService;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush command file.
 */
class MediaContextualCropCommands extends DrushCommands {

  /**
   * Instance of config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;

  /**
   * Instance of config.storage service.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  private $configStorage;

  /**
   * Instance of entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * Instance of MediaContextualCrop service.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropService
   */
  private $mccService;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactory $config_factory_service,
    CachedStorage $config_storage_service,
    EntityTypeManager $entity_type_manager_service,
    MediaContextualCropService $mccService,
  ) {
    $this->configFactory = $config_factory_service;
    $this->configStorage = $config_storage_service;
    $this->entityTypeManager = $entity_type_manager_service;
    $this->mccService = $mccService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.storage'),
      $container->get('entity_type.manager'),
      $container->get('media_contextual_crop.service'),
    );
  }

  /**
   * Command description here.
   *
   * @usage media_contextual_crop-migrateToImageFormatter
   *   Usage description
   *
   * @command media_contextual_crop:migrateToImageFormatter
   */
  public function migrateImageFormatter() {

    $count = 0;
    $configNames = $this->configStorage->listAll('core.entity_view_display');

    // Get all Entity display config.
    foreach ($configNames as $config_name) {
      $conf = $this->configFactory->getEditable($config_name);
      $conf_change = FALSE;

      // Only process Medias.
      if ($conf->get('targetEntityType') !== 'media') {
        continue;
      }

      $dependencies = $conf->get('dependencies');
      if (isset($dependencies['module']) && in_array("media_contextual_crop", $dependencies['module'])) {

        // Find image formatters.
        $content = $conf->get('content');
        foreach ($content as $field_name => $field_data) {
          if (isset($field_data['type']) && $field_data['type'] == 'contextual_image') {

            // If Image style use crop, change formatter.
            $style_name = $field_data['settings']['image_style'];
            if ($this->mccService->styleUseMultiCrop($style_name)) {
              $content[$field_name]['type'] = 'image';
              $conf->set('content', $content);
              $conf_change = TRUE;
            }
          }
        }
      }

      if ($conf_change === TRUE) {
        $count++;
        $this->logger()->success(dt('Changing ' . $config_name));
        // Save conf.
        $conf->save();
        // Update entity data;.
        $entity_name = str_replace('core.entity_view_display.', '', $config_name);
        $storage = $this->entityTypeManager->getStorage('entity_view_display');
        $view_display = $storage->load($entity_name);
        $view_display->save();
      }
    }

    if ($count === 0) {
      $this->logger()->success(dt('No configuration changed.'));
    }
    else {
      $this->logger()->success(dt('Some configuration changed, please check for possible corrections.'));
    }

  }

}
