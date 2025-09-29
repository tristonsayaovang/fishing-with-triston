<?php

/**
 * @file
 * Contains alteration on native image_responsive formatter.
 */

use Drupal\responsive_image\Entity\ResponsiveImageStyle;

/**
 * Alter responsive formatters.
 *
 * @param array $variables
 *   Formatter variables array.
 * @param string $key
 *   Formatter Key.
 */
function _media_contextual_crop_responsive_formatters(&$variables, string $key) {

  // Find the competent plugin to process this image.
  $use_case_plugin_manager = \Drupal::service('plugin.manager.media_contextual_crop_use_case');
  $use_case_plugin = $use_case_plugin_manager->findCompetent($variables['item']);

  if ($use_case_plugin != NULL) {

    // Pregenerate crop settings.
    $crop_settings = $use_case_plugin->getCropSettings($variables['item']);

    if ($crop_settings != NULL) {
      // Inject crop settings.
      $variables[$key]['#attributes']['#mcc'] = $crop_settings;
    }

  }
}

/**
 * Alter responsive item (responsive_image / CQRI)
 */
function _media_contextual_crop_preprocess_responsive_item(&$variables) {

  // If crop settings exists.
  if (isset($variables['attributes']['#mcc'])) {

    // Recover crop settings.
    $crop_settings = $variables['attributes']['#mcc'];

    // Clean un-needed attributes.
    unset($variables['attributes']['#mcc']);
    unset($variables['img_element']['#attributes']['#mcc']);

    // Get Responsive config data.
    $responsive_image_style = ResponsiveImageStyle::load($variables['responsive_image_style_id']);

    if ($responsive_image_style != NULL) {
      // Make old image stats.
      $old_image = [
        '#uri' => $variables['uri'],
        '#width' => $variables['width'],
        '#height' => $variables['height'],
      ];

      // Find styles.
      $style_mapping = $responsive_image_style->getImageStyleMappings();
      $style_set = [];
      foreach ($style_mapping as $data) {
        if (!is_array($data['image_mapping'])) {
          $style_set[] = $data['image_mapping'];
        }
        else {
          $style_set = [...$style_set, ...$data['image_mapping']['sizes_image_styles']];
        }
      }

      $style_set = array_unique($style_set);

      // Generate copies images and get URIs.
      $new_uris = [];
      $at_least_one = FALSE;
      $media_contextual_crop_service = \Drupal::service('media_contextual_crop.service');
      // Do it for style set.
      foreach ($style_set as $style) {

        if ($media_contextual_crop_service->styleUseMultiCrop($style)) {
          $crop_settings['image_style'] = $style;
          $new_base = $media_contextual_crop_service->generateContextualizedImage($old_image, $crop_settings);

          $new_uris[$style] = $new_base;
          $at_least_one = TRUE;
        }
        else {
          // $new_uris[$style] = NULL;
        }
      }

      if ($at_least_one === TRUE) {
        // If sources are set, swap the core URI with the newly created URI.
        if (isset($variables['sources'])) {

          foreach ($variables['sources'] as $source_attribute) {
            if ($source_attribute->hasAttribute('srcset')) {
              $original_srcset = $source_attribute->offsetGet('srcset')->value();
              $src_sets = explode(',', $original_srcset);
              $new_src_sets = array_map(static function ($srcset) use ($new_uris, $style_set) {
                foreach ($style_set as $style_name) {
                  // Add a slash to the beginning and end of the image style
                  // name. This is to avoid false positives when checking if the
                  // srcset value contains the style name as "image_style_name"
                  // will match "image_style_name" and "image_style_name_2x".
                  $check_style_name = '/' . $style_name . '/';
                  if (str_contains($srcset, $check_style_name) && isset($new_uris[$style_name])) {
                    return $new_uris[$style_name];
                  }
                }
              }, $src_sets);

              if ($new_src_sets != [NULL]) {
                $source_attribute->setAttribute('srcset', implode(', ', $new_src_sets));
                $variables['img_element']['#attributes']['srcset'] = $variables['attributes']['srcset'] = $source_attribute->offsetGet('srcset');
              }

            }
          }
        }

      }

      // Manage Fallback Image.
      $fallback = $responsive_image_style->getFallbackImageStyle();

      if ($media_contextual_crop_service->styleUseMultiCrop($fallback)) {
        $crop_settings['image_style'] = $fallback;
        $new_uri = $media_contextual_crop_service->generateContextualizedImage($old_image, $crop_settings);

        // Replace image fallback URI.
        $variables['img_element']['#attributes']['uri'] = $new_uri;
        $variables['img_element']['#uri'] = $new_uri;

      }

    }

  }

}
