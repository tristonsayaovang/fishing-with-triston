<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * Interface for media_contextual_crop_use_case plugins.
 */
interface MediaContextualCropUseCaseInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label();

  /**
   * The Plugin is competent to process this image.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   Image Item.
   *
   * @return bool
   *   True or false.
   */
  public function isCompetent(ImageItem $item);

  /**
   * Generate Crop settings for current image.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem $item
   *   Image Item Data.
   *
   * @return array|null
   *   Return Crop settings or NULL
   */
  public function getCropSettings(ImageItem $item);

  /**
   * Retrieve contextualized derivative URL.
   *
   * @param array $crop_settings
   *   Crop settings to apply on original image.
   * @param string $old_uri
   *   Uri of current image.
   * @param string $image_style
   *   Image style to apply.
   *
   * @return string
   *   Url to use.
   */
  public function getContextualizedImage($crop_settings, $old_uri, $image_style);

}
