<?php

namespace Drupal\media_library_media_modify;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\media_library\MediaLibraryFieldWidgetOpener;
use Drupal\media_library\MediaLibraryState;

/**
 * Override the field widget opener.
 *
 * Necessary to disable settings after the dialog was closed.
 *
 * @phpstan-ignore-next-line
 */
class MediaLibraryMediaModifyFieldWidgetOpener extends MediaLibraryFieldWidgetOpener {

  /**
   * {@inheritdoc}
   */
  public function getSelectionResponse(MediaLibraryState $state, array $selected_ids): AjaxResponse {
    $response = parent::getSelectionResponse($state, $selected_ids);
    $response->addAttachments([
      'drupalSettings' => [
        'media_library_media_modify' => [
          'replace_checkbox_by_order_indicator' => FALSE,
        ],
      ],
    ]);
    return $response;
  }

}
