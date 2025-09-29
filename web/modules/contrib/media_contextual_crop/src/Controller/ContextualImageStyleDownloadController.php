<?php

namespace Drupal\media_contextual_crop\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\ImageStyleInterface;
use Drupal\media_contextual_crop\MediaContextualCropService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines a controller to serve image styles.
 */
class ContextualImageStyleDownloadController extends ImageStyleDownloadController {

  /**
   * MediaContextualCrop Service.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropService
   */
  protected $mccService;

  /**
   * {@inheritdoc}
   */
  public function __construct(LockBackendInterface $lock, ImageFactory $image_factory, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system, MediaContextualCropService $mcc_service) {
    parent::__construct($lock, $image_factory, $stream_wrapper_manager, $file_system);
    $this->mccService = $mcc_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lock'),
      $container->get('image.factory'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->get('media_contextual_crop.service')
    );
  }

  /**
   * Generates a contextual derivative, given a style and image path.
   *
   * After generating an image, transfer it to the requesting agent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The file scheme, defaults to 'private'.
   * @param int $context
   *   The context of the derivative.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to deliver.
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The transferred file as response or some error response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the file request is invalid.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   * @throws \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
   *   Thrown when the file is still being generated.
   *
   * @see: ImageStyleDownloadController:deliver();
   */
  public function process(Request $request, int $context, ImageStyleInterface $image_style, $scheme) {

    // Push context to request to the crop processing (see Patch on CROP).
    $request->query->set('contextual_crop', $context);

    // Load context.
    $crop = $this->entityTypeManager()->getStorage('crop')->load($context);

    // Get the URI for cropped file.
    $crop_uri = $crop->uri->value;
    $target = str_replace($scheme . '://', '', $crop_uri);
    $original_image_uri = $this->streamWrapperManager->normalizeUri($crop_uri);

    // Factorization of ImageStyleDownloadController::deliver Line 114-124.
    // Throw NotFoundHttpException.
    $this->checkNormalizedScheme($scheme, $original_image_uri);

    // Check if token is valid.
    // Factorization of ImageStyleDownloadController::deliver Line 139-142.
    $token_is_valid = $this->checkToken($request, $original_image_uri, $scheme, $target, $image_style);

    // Factorization of ImageStyleDownloadController::deliver Line 126-152.
    // Throw NotFoundHttpException.
    $this->authorizedDerivativeGeneration($image_style, $scheme, $target, $token_is_valid);

    $derivative_uri = $this->mccService->createContextualizedDerivativePath($original_image_uri, $image_style->id(), $context, TRUE);

    $derivative_scheme = $this->streamWrapperManager->getScheme($derivative_uri);

    // Factorization of ImageStyleDownloadController::deliver Line 157-165.
    $is_public = $this->isSchemePublic($token_is_valid, $scheme, $derivative_scheme);

    $headers = [];

    // If not using a public scheme, let other modules provide headers and
    // control access to the file.
    if (!$is_public) {
      $headers = $this->moduleHandler()->invokeAll('file_download', [$original_image_uri]);
      if (in_array(-1, $headers) || empty($headers)) {
        throw new AccessDeniedHttpException();
      }
    }

    // Don't try to generate file if source is missing.
    if (!$this->sourceImageExists($original_image_uri, $token_is_valid)) {
      // If the image style converted the extension, it has been added to the
      // original file, resulting in filenames like image.png.jpeg. So to find
      // the actual source image, we remove the extension and check if that
      // image exists.
      $path_info = pathinfo(StreamWrapperManager::getTarget($original_image_uri));
      $converted_image_uri = sprintf('%s://%s%s%s', $this->streamWrapperManager->getScheme($derivative_uri), $path_info['dirname'], DIRECTORY_SEPARATOR, $path_info['filename']);
      if (!$this->sourceImageExists($converted_image_uri, $token_is_valid)) {
        $this->logger->notice(
          'Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.',
          ['%source_image_path' => $original_image_uri, '%derivative_path' => $derivative_uri]);
        return new Response($this->t('Error generating image, missing source file.'), 404);
      }
      else {
        // The converted file does exist, use it as the source.
        $original_image_uri = $converted_image_uri;
      }
    }

    // Don't start generating the image if the derivative already exists or if
    // generation is in progress in another thread.
    if (!file_exists($derivative_uri)) {
      $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($derivative_uri);
      $lock_acquired = $this->lock->acquire($lock_name);
      if (!$lock_acquired) {
        // Tell client to retry again in 3 seconds. Currently no browsers are
        // known to support Retry-After.
        throw new ServiceUnavailableHttpException(3, 'Image generation in progress. Try again shortly.');
      }
    }

    // Try to generate the image, unless another thread just did it while we
    // were acquiring the lock.
    $success = file_exists($derivative_uri) || $image_style->createDerivative($original_image_uri, $derivative_uri);

    if (!empty($lock_acquired)) {
      $this->lock->release($lock_name);
    }

    if ($success) {
      $image = $this->imageFactory->get($derivative_uri);
      $uri = $image->getSource();
      $headers += [
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
      // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
      // sets response as not cacheable if the Cache-Control header is not
      // already modified. When $is_public is TRUE, the following sets the
      // Cache-Control header to "public".
      return new BinaryFileResponse($uri, 200, $headers, $is_public);
    }
    else {
      $this->logger->notice('Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
      return new Response($this->t('Error generating image.'), 500);
    }
  }

}
