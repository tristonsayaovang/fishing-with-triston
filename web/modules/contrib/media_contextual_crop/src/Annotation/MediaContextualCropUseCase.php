<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines media_contextual_crop_use_case annotation object.
 *
 * @Annotation
 */
class MediaContextualCropUseCase extends Plugin {

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

}
