<?php

namespace Drupal\media_contextual_crop\Plugin\Field\FieldFormatter;

use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter as BaseFromImage;

/**
 * Plugin implementation of the 'contextual image' formatter.
 *
 * @see: Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter
 *
 * @FieldFormatter(
 *   id = "contextual_image",
 *   label = @Translation("DEPRECATED Contextual Crop Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class ImageFormatter extends BaseFromImage {


}
