<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

/**
 * Interface for media_contextual_crop plugins.
 */
interface MediaContextualCropInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * Returns the Target Field of the plugin.
   *
   * @return string
   *   Target field name.
   */
  public function getTargetFieldName();

  /**
   * Save override crop.
   *
   * @param mixed $crop_settings
   *   Overwritten crop data.
   * @param string $image_style_name
   *   target Image Style.
   * @param string $old_uri
   *   Uri of original image.
   * @param string $context
   *   Crop context.
   * @param int $width
   *   Source Image Width.
   * @param int $height
   *   Source Image Height.
   *
   * @return bool
   *   If a custom crop can be applied.
   */
  public function saveCrop($crop_settings, string $image_style_name, string $old_uri, string $context, int $width, int $height);

  /**
   * Process Field data to save them.
   *
   * @param array $field_data
   *   Field data.
   *
   * @return mixed
   *   Data to save in overrides.
   */
  public static function processFieldData(array $field_data);

  /**
   * Process Embed settings to make crop settings.
   *
   * @param mixed $embed_settings
   *   Embed settings.
   *
   * @return array
   *   Crop settings.
   */
  public function processEmbedData($embed_settings);

}
