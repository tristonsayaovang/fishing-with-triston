<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageUrlFormatter;
use Drupal\media_contextual_crop\MediaContextualCropUseCasePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementation of an 'image_url' formatter which return contextualized URI.
 *
 * @FieldFormatter(
 *   id = "image_contextual_url",
 *   label = @Translation("Contextual URL to image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageContextualUrlFormatter extends ImageUrlFormatter {

  /**
   * Contextual Crop Use Case Plugin Manager.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropUseCasePluginManager
   */
  private $mediaContextualCropUseCasePluginManager;

  /**
   * File URI Generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  private $fileUrlGenerator;

  /**
   * Constructs an ImageFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\media_contextual_crop\MediaContextualCropUseCasePluginManager $mediaContextualCropUseCasePluginManager
   *   Contextual Crop Use Case Plugin Manager.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   File URL generator Service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityStorageInterface $image_style_storage, AccountInterface $current_user, MediaContextualCropUseCasePluginManager $mediaContextualCropUseCasePluginManager, FileUrlGeneratorInterface $fileUrlGenerator) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, $image_style_storage, $current_user);
    $this->mediaContextualCropUseCasePluginManager = $mediaContextualCropUseCasePluginManager;
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('current_user'),
      $container->get('plugin.manager.media_contextual_crop_use_case'),
      $container->get('file_url_generator')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @see ImageUrlFormatter::viewElements
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
    if (empty($images = $this->getEntitiesToView($items, $langcode))) {
      // Early opt-out if the field is empty.
      return $elements;
    }

    /** @var \Drupal\image\ImageStyleInterface $image_style */
    $image_style = $this->imageStyleStorage->load($this->getSetting('image_style'));

    /** @var \Drupal\file\FileInterface[] $images */
    foreach ($images as $delta => $image) {
      $image_uri = $image->getFileUri();

      // Try to find a contextual crop from a reference field.
      $item = $image->_referringItem;
      $use_case_plugin = $this->mediaContextualCropUseCasePluginManager->findCompetent($item);
      if ($use_case_plugin != NULL) {
        $context_settings = $use_case_plugin->getCropSettings($item);
        $context_settings['plugin'] = $use_case_plugin->getPluginId();
        $context_settings['item_values'] = $item->getValue();

        $url = $use_case_plugin->getContextualizedImage($context_settings, $image_uri, $image_style->id());
      }
      else {
        $url = $image_style ? $this->fileUrlGenerator->transformRelative($image_style->buildUrl($image_uri)) : $this->fileUrlGenerator->generateString($image_uri);
      }

      // Add cacheability metadata from the image and image style.
      $cacheability = CacheableMetadata::createFromObject($image);
      if ($image_style) {
        $cacheability->addCacheableDependency(CacheableMetadata::createFromObject($image_style));
      }

      $elements[$delta] = ['#markup' => $url];
      $cacheability->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
