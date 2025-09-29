<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines multi_crop annotation object.
 *
 * @Annotation
 */
class MediaContextualCrop extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The description of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The Target Field Name.
   *
   * @var string
   */
  public $target_field_name;

  /**
   * Image Style Effect associated.
   *
   * @var array
   */
  public $image_style_effect;

}
