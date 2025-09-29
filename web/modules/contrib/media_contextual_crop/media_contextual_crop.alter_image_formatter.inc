<?php

/**
 * @file
 * Contains alteration on native image formatter.
 */

use Drupal\image\Entity\ImageStyle;

/**
 * Implements hook_preprocess_image_formatter().
 *
 * @Desc : intercept classic image formatter
 */
function media_contextual_crop_preprocess_image_formatter(&$variables) {

  // Find the competent plugin to process this image.
  $use_case_plugin_manager = \Drupal::service('plugin.manager.media_contextual_crop_use_case');
  /** @var \Drupal\media_contextual_crop\MediaContextualCropUseCasePluginBase $use_case_plugin */
  $use_case_plugin = $use_case_plugin_manager->findCompetent($variables['item']);

  if ($use_case_plugin != NULL) {
    $context_settings = $use_case_plugin->getCropSettings($variables['item']);
    $context_settings['plugin'] = $use_case_plugin->getPluginId();
    $context_settings['item_values'] = $variables['item']->getValue();
    $variables['image']['#theme'] = 'image_contextual';
    $variables['image']['#attributes']['contextual'] = $context_settings;
  }
}

/**
 * Prepares variables for image style templates.
 *
 * Default template: image-contextual.html.twig.
 *
 * @see template_preprocess_image_style()
 */
function template_preprocess_image_contextual(&$variables) {
  \Drupal::moduleHandler()->loadInclude('image', 'field.inc');
  template_preprocess_image_style($variables);

  $contextual_settings = $variables['image']['#attributes']['contextual'];
  unset($variables['image']['#attributes']['contextual']);

  /** @var \Drupal\media_contextual_crop\MediaContextualCropUseCasePluginManager $use_case_plugin_manager */
  $use_case_plugin_manager = \Drupal::service('plugin.manager.media_contextual_crop_use_case');
  $use_case_plugin = $use_case_plugin_manager->createInstance($contextual_settings['plugin']);

  $style = ImageStyle::load($variables['style_name']);

  if ($style->supportsUri($variables['uri']) && $use_case_plugin != NULL) {

    $new_uri = $use_case_plugin->getContextualizedImage($contextual_settings, $variables['uri'], $variables['image']['#style_name']);

    // Replace original image URI by the contextualized copy URI.
    if ($new_uri != NULL) {
      $variables['image']['#uri'] = $new_uri;
    }

  }

}
