<?php

namespace Drupal\media_contextual_crop\Routing;

use Drupal\image\Routing\ImageStyleRoutes as BaseFromImage;
use Symfony\Component\Routing\Route;

/**
 * Defines a route subscriber to register a URL to serve contextual images.
 *
 * @see \Drupal\image\Routing\ImageStyleRoutes;
 */
class ImageStyleRoutes extends BaseFromImage {

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];
    // Generate image derivatives of publicly available files. If clean URLs are
    // disabled image derivatives will always be served through the menu system.
    // If clean URLs are enabled and the image derivative already exists, PHP
    // will be bypassed.
    $directory_path = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();

    $routes['media_contextual_crop.style_public'] = new Route(
      '/' . $directory_path . '/contextual/styles/{image_style}/{context}/{scheme}',
      [
        '_controller' => 'Drupal\media_contextual_crop\Controller\ContextualImageStyleDownloadController::process',
      ],
      [
        '_access' => 'TRUE',
        'context' => '\d+',
      ]
    );
    return $routes;
  }

}
