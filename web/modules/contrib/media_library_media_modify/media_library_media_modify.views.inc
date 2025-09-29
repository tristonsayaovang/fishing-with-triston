<?php

/**
 * @file
 * Contains Views integration for the media_library_media_modify module.
 */

/**
 * Implements hook_views_data().
 */
function media_library_media_modify_views_data(): array {
  $data = [];

  $data['media']['media_library_media_modify_edit_link'] = [
    'title' => t('Edit link for the Media Library'),
    'help' => t('Provides a link for editing media entities from within the media library.'),
    'real field' => 'mid',
    'field' => [
      'id' => 'media_library_media_modify_edit_link',
    ],
  ];
  return $data;
}
