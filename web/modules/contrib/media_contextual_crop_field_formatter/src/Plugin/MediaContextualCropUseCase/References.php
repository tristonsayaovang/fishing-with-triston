<?php

namespace Drupal\media_contextual_crop_field_formatter\Plugin\MediaContextualCropUseCase;

use Drupal\Component\Serialization\Json;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;
use Drupal\media_contextual_crop\MediaContextualCropUseCasePluginBase;

/**
 * Plugin implementation of the media_contextual_crop_use_case.
 *
 * @MediaContextualCropUseCase(
 *   id = "references",
 *   label = @Translation("References"),
 *   default_folder = "media_contextual_crop"
 * )
 */
class References extends MediaContextualCropUseCasePluginBase {

  /**
   * {@inheritdoc}
   */
  public function isCompetent(ImageItem $item) {

    $entity_parent = $item->getEntity() ?? NULL;
    if (!($entity_parent instanceof MediaInterface)) {
      return FALSE;
    }

    $crop_context_ref = $entity_parent->entity_reference_entity_modify ?? NULL;
    if ($crop_context_ref == NULL || $entity_parent->_referringItem == NULL) {
      return FALSE;
    }

    $field_definition = $entity_parent->getSource()
      ->getSourceFieldDefinition($entity_parent->bundle->entity);
    $item_class = $field_definition->getItemDefinition()->getClass();
    if ($item_class != ImageItem::class && !is_subclass_of($item_class, ImageItem::class)) {
      return FALSE;
    }

    return TRUE;

  }

  /**
   * Generate Crop settings for current image.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   Image Item Data.
   *
   * @return array|null
   *   Return Crop settings or NULL
   */
  public function getCropSettings(ImageItem $item) {
    // Get parent entity of the image if exists.
    $entity_parent = $item->getEntity() ?? NULL;
    if (!($entity_parent instanceof MediaInterface)) {
      return NULL;
    }

    $crop_data = [];

    // Get contextual modification, if exists.
    $crop_context_ref = $entity_parent->entity_reference_entity_modify ?? NULL;
    if ($crop_context_ref != NULL && $referringItem = $entity_parent->_referringItem) {
      $media_override = $referringItem->getValue() ?? [];
      // If there is overwritten data.
      if (isset($media_override['overwritten_property_map'])) {
        $crop_data = Json::decode($media_override['overwritten_property_map']);
      }
    }
    else {
      return NULL;
    }

    // Recover field image in the media.
    $media_image_field = NULL;
    $field_definition = $entity_parent->getSource()
      ->getSourceFieldDefinition($entity_parent->bundle->entity);
    $item_class = $field_definition->getItemDefinition()->getClass();
    if ($item_class == ImageItem::class || is_subclass_of($item_class, ImageItem::class)) {
      $media_image_field = $field_definition->getName();
    }
    else {
      return NULL;
    }

    if ($crop_data != [] && $media_image_field != NULL) {

      foreach ($crop_data as $field_name => $data_values) {
        if ($media_image_field == $field_name) {

          // Prepare Crop Data.
          $crop_settings = $this->getPluginSetting($data_values[0]);
          if (isset($crop_settings['plugin_id'])) {
            $crop_settings['context'] = $crop_context_ref;
            $crop_settings['base_crop_folder'] = 'media_contextual_crop';

            return $crop_settings;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Recover Crop settings.
   *
   * @param array $configuration
   *   Field information.
   *
   * @return array
   *   Crop settings.
   */
  private function getPluginSetting(array $configuration) {

    $plugin_definitions = $this->mccPluginManager->getDefinitions();

    foreach ($plugin_definitions as $plugin_id => $plugin) {
      foreach ($configuration as $key => $value) {

        $target_field = $plugin['target_field_name'];
        if ($key === $target_field) {
          return [
            'plugin_id' => $plugin_id,
            'crop_setting' => $value,
          ];
        }
      }
    }
    return [];
  }

}
