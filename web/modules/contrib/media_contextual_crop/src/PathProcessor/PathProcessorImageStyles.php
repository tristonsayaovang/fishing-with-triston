<?php

namespace Drupal\media_contextual_crop\PathProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\crop\CropInterface;
use Drupal\image\PathProcessor\PathProcessorImageStyles as BaseFromImage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Defines a path processor to rewrite contextualized image styles URLs.
 *
 * @see \Drupal\image\PathProcessor\PathProcessorImageStyles;
 */
class PathProcessorImageStyles extends BaseFromImage {

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PathProcessorImageStyles object.
   *
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Get entity_type_manager.
   */
  public function __construct(StreamWrapperManagerInterface $stream_wrapper_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {

    $directory_path = $this->streamWrapperManager->getViaScheme('public')->getDirectoryPath();
    if (strpos($path, '/' . $directory_path . '/contextual/') === 0) {
      $path_prefix = '/' . $directory_path . '/contextual/';
    }
    // Check if the string '/system/files/styles/' exists inside the path,
    // that means we have a case of private file's image style.
    elseif (str_contains($path, '/system/files/contextual/')) {
      $path_prefix = '/system/files/contextual/';
      $path = substr($path, strpos($path, $path_prefix), strlen($path));
    }
    else {
      return $path;
    }

    // Strip out path prefix.
    $rest = preg_replace('|^' . preg_quote($path_prefix, '|') . '|', '', $path);

    // Get the image style, scheme and path.
    if (substr_count($rest, '/') >= 5) {

      $exploded_path = explode('/', $rest);
      /*
       * $exploded_path[0] = "styles"
       * $exploded_path[1] = style_name
       * $exploded_path[2] = scheme (ex : "public")
       * $exploded_path[3] -> [n-1] => file_path
       * $exploded_path[n] = crop_id '.' extension
       */
      $crop_extension = array_pop($exploded_path);
      [$crop_id, $extension] = explode('.', $crop_extension);
      unset($extension);

      // Reconstruct original image path.
      $original_image_path = implode('/', array_slice($exploded_path, 3));
      $original_image_path = $exploded_path[2] . '://' . $original_image_path;

      // Load crop.
      $crop = $this->entityTypeManager->getStorage('crop')->load($crop_id);
      if ($crop instanceof CropInterface) {
        $crop_uri = $crop->uri->value;

        // Ensure Crop Target and Requested Image are the same.
        $path_parts = pathinfo($crop_uri);
        $crop_target_filename = $path_parts['dirname'] . '/' . str_replace('.', '__', $path_parts['filename']) . '__' . $path_parts['extension'];
        if ($original_image_path != $crop_target_filename) {
          throw new NotFoundHttpException();
        }

        // Push Source image to request, in order validate private DL.
        $request->query->set('file', $crop_uri);
      }
      // Set URL for controller.
      $url = $path_prefix . 'styles/' . $exploded_path[1] . '/' . $crop_id . '/' . $exploded_path[2];
      return $url;
    }

    return $path;
  }

}
