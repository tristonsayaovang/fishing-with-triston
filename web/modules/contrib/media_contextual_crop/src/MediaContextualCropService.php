<?php

declare(strict_types=1);

namespace Drupal\media_contextual_crop;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Routing\RequestHelper;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Url;
use Drupal\crop\Entity\Crop;
use Drupal\image\Entity\ImageStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service description.
 */
class MediaContextualCropService {

  /**
   * The plugin.manager.media_contextual_crop service.
   *
   * @var \Drupal\media_contextual_crop\MediaContextualCropPluginManager
   */
  protected $contextualCropManager;

  /**
   * The FileSystem service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The Stream Wrapper Service.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The Get EntityTypeManagerInterface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;


  /**
   * The File URL Generator Service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The File URL Generator Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The ConfigFactory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a ContextualCropService object.
   *
   * @param \Drupal\media_contextual_crop\MediaContextualCropPluginManager $contextualCrop_manager
   *   The plugin.manager.media_contextual_crop service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The FileSystem service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The Stream Wrapper Service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Get entity_type_manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The FileUrlGenerator service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The HTTP Request.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The ConfigFactory service.
   */
  public function __construct(MediaContextualCropPluginManager $contextualCrop_manager, FileSystemInterface $file_system, StreamWrapperManagerInterface $streamWrapperManager, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, FileUrlGeneratorInterface $fileUrlGenerator, RequestStack $requestStack, ConfigFactory $configFactory) {
    $this->contextualCropManager = $contextualCrop_manager;
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $streamWrapperManager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->requestStack = $requestStack;
    $this->configFactory = $configFactory;
  }

  /**
   * Get Contextualized image URI.
   *
   * @param array $old_image
   *   An image render array containing (at least):
   *   - #uri: the image uri
   *   - #width: the image width
   *   - #height: the image heigh.
   * @param array $settings
   *   The render array use for cropping with :
   *   - plugin_id : the crop plugin used
   *   - [Crop settings] (from the crop widget)
   *   - context : the media field identifier context
   *   - image_style : image style needed.
   *
   * @return string
   *   The URI of cropped image.
   */
  public function generateContextualizedImage(array $old_image, array $settings) {

    // Load dedicated multi crop plugin.
    $crop_type = $settings['plugin_id'];
    $plugin = $this->contextualCropManager->createInstance($crop_type);

    // Generate crop.
    $crop_id = $plugin->saveCrop(
      $settings['crop_setting'],
      $settings['image_style'],
      $old_image['#uri'],
      $settings['context'],
      (int) $old_image['#width'],
      (int) $old_image['#height'],
    );

    // Generate new URI.
    return $this->createContextualizedDerivativePath($old_image['#uri'], $settings['image_style'], $crop_id);
  }

  /**
   * Generate contextualized URI (and clean derivative if already exists).
   *
   * @param string $old_image_uri
   *   Source image URI.
   * @param string $image_style
   *   Image style used.
   * @param int $crop_id
   *   Contextualized crop to used.
   * @param bool $just_uri
   *   Only return the URI, not full URL & tokens.
   *
   * @return string
   *   Image path.
   */
  public function createContextualizedDerivativePath($old_image_uri, $image_style, $crop_id, $just_uri = FALSE) {
    $clean_urls = NULL;

    $style = $this->entityTypeManager->getStorage('image_style')->load($image_style);

    $target = $this->streamWrapperManager::getTarget($old_image_uri);
    $source_scheme = $scheme = $this->streamWrapperManager::getScheme($old_image_uri);

    // Calculate new extension.
    $original_extension = pathinfo($target, PATHINFO_EXTENSION);
    $new_extension = $style->getDerivativeExtension($original_extension);

    // Slice old extension from target.
    $target_as_folder = str_replace('.', '__', $target);

    // Create new URI.
    $path = $scheme . '://contextual/styles/' . $image_style . '/' . $source_scheme . '/' . $target_as_folder . '/' . $crop_id . '.' . $new_extension;
    $uri = $this->streamWrapperManager->normalizeUri($path);

    if ($just_uri === TRUE) {
      return $uri;
    }
    // Recover ImageStyle::buildUrl.
    // The token query is added even if the
    // 'image.settings:allow_insecure_derivatives' configuration is TRUE, so
    // that the emitted links remain valid if it is changed back to the default
    // FALSE. However, sites which need to prevent the token query from being
    // emitted at all can additionally set the
    // 'image.settings:suppress_itok_output' configuration to TRUE to achieve
    // that (if both are set, the security token will neither be emitted in the
    // image derivative URL nor checked for in
    // \Drupal\image\ImageStyleInterface::deliver()).
    $token_query = [];
    if (!$this->configFactory->get('image.settings')->get('suppress_itok_output')) {
      $normalize_old_original_uri = $this->streamWrapperManager->normalizeUri($old_image_uri);
      $token_query = [IMAGE_DERIVATIVE_TOKEN => $style->getPathToken($normalize_old_original_uri)];
    }

    // Found a crop for this image, append a hash of it to the URL,
    // so that browsers reload the image and CDNs and proxies can be bypassed.
    if ($crop = Crop::load($crop_id)) {
      $token_query['h'] = $this->getCropHashToken($crop);
    }

    if ($clean_urls === NULL) {
      // Assume clean URLs unless the request tells us otherwise.
      $clean_urls = TRUE;
      try {
        $clean_urls = RequestHelper::isCleanUrl($this->requestStack->getCurrentRequest());
      }
      catch (ServiceNotFoundException $e) {
      }
    }

    // If not using clean URLs, the image derivative callback is only available
    // with the script path. If the file does not exist, use Url::fromUri() to
    // ensure that it is included. Once the file exists it's fine to fall back
    // to the actual file path, this avoids bootstrapping PHP once the files are
    // built.
    if ($clean_urls === FALSE && $this->streamWrapperManager::getScheme($uri) == 'public' && !file_exists($uri)) {
      $directory_path = $this->streamWrapperManager->getViaUri($uri)->getDirectoryPath();
      return Url::fromUri(
        'base:' . $directory_path . '/' . $this->streamWrapperManager::getTarget($uri),
        [
          'absolute' => TRUE,
          'query' => $token_query,
        ]
      )->toString();
    }

    $file_url = $this->fileUrlGenerator->generateString($uri);
    // Append the query string with the token, if necessary.
    if ($token_query) {
      $file_url .= (str_contains($file_url, '?') ? '&' : '?') . UrlHelper::buildQuery($token_query);
    }

    return $file_url;
  }

  /**
   * Generate a Hash for the Crop in order to bypass CDN Caches if crop change.
   *
   * @param \Drupal\crop\Entity\Crop $crop
   *   Crop object.
   *
   * @return string
   *   Hash token of crop.
   */
  public function getCropHashToken(Crop $crop) {
    return substr(md5(implode($crop->position()) . implode($crop->anchor())), 0, 8);
  }

  /**
   * Check if style use a crop manage by MCC.
   *
   * @param string $style_name
   *   Image style name.
   *
   * @return bool
   *   Check if style use a crop manage by MCC.
   */
  public function styleUseMultiCrop($style_name) {

    $image_style = $this->entityRepository->loadEntityByConfigTarget('image_style', $style_name);

    if ($image_style != NULL) {
      $style_crop = $this->getCropTypeByImageStyle($image_style);
      return count($style_crop) > 0;
    }

    return FALSE;
  }

  /**
   * Get all crop types by image style.
   *
   * @param \Drupal\image\Entity\ImageStyle $image_style
   *   Image Style.
   *
   * @return array
   *   Crop used by Image style.
   */
  private function getCropTypeByImageStyle(ImageStyle $image_style) {

    static $styles = [];
    $style_name = $image_style->id();
    if (!isset($styles[$style_name])) {
      $styles[$style_name] = [];

      $effects = $image_style->getEffects()->getConfiguration();
      $plugins = $this->contextualCropManager->getDefinitions();

      // Find crops used in image style.
      foreach ($effects as $effect) {
        foreach ($plugins as $plugin) {
          if (in_array($effect['id'], $plugin['image_style_effect'])) {
            $styles[$style_name][] = $effect['data']['crop_type'];
          }
        }
      }
    }

    return $styles[$style_name];
  }

  /**
   * Flush contextuals derivative of the style.
   *
   * @param \Drupal\image\Entity\ImageStyle $imageStyle
   *   Image Style.
   * @param string|null $path
   *   (optional) The original image path or URI. If supplied, only derivatives
   *   for this specific image will be flushed.
   */
  public function flushStyle(ImageStyle $imageStyle, $path = NULL) {
    $folder_uri = $this->buildContextualFolderPath($imageStyle->id(), $path);
    if ($path !== NULL) {
      // Only delete the specific contextual derivatives for this image.
      if (is_dir($folder_uri)) {
        $this->fileSystem->deleteRecursive($folder_uri);
      }
    }
    else {
      $this->fileSystem->deleteRecursive($folder_uri);
    }
  }

  /**
   * Build the contextual folder path for a specific image and image style.
   *
   * This method builds the folder path where contextual derivatives for a
   * specific image would be stored, using the same scheme
   * as the original image.
   *
   * @param string $image_style_id
   *   The image style ID.
   * @param string $image_uri
   *   The original image URI.
   *
   * @return string
   *   The contextual folder path.
   */
  public function buildContextualFolderPath($image_style_id, $image_uri) {
    $folder_uri = 'public://contextual/styles/' . $image_style_id;
    if ($image_uri !== NULL) {
      $scheme = $this->streamWrapperManager::getScheme($image_uri);
      $target = $this->streamWrapperManager::getTarget($image_uri);
      $target_as_folder = str_replace('.', '__', $target);
      $folder_uri .= '/' . $scheme . '/' . $target_as_folder;
    }

    return $folder_uri;
  }

  /**
   * Flush all derivative generate from one crop.
   *
   * @param \Drupal\crop\Entity\Crop $crop
   *   Crop deleted.
   */
  public function deleteDerivative(Crop $crop) {
    // Load all image styles used by the current crop type.
    $image_style_ids = $this->entityTypeManager->getStorage('image_style')
      ->getQuery()
      ->condition('effects.*.data.crop_type', $crop->bundle())
      ->accessCheck(TRUE)
      ->execute();

    // For each image style.
    foreach ($image_style_ids as $style_id) {
      // Generate attempted derivative path for this crop.
      $uri = $this->createContextualizedDerivativePath($crop->uri->getString(), $style_id, $crop->id(), TRUE);

      // If derivative exists.
      if (file_exists($uri)) {
        $this->fileSystem->unlink($uri);
      }
    }
  }

  /**
   * Recover crops from an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity source.
   * @param string $field_name
   *   Field name.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Crops.
   */
  public function getContextualCrops(EntityInterface $entity, string $field_name = '') {

    $base_context = $this->getBaseContext($entity, $field_name);

    return $this->getContextualCropsFromBaseContext($base_context);
  }

  /**
   * Return a base context from entity & field_name.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity source.
   * @param string $field_name
   *   Field name.
   * @param int $delta
   *   Field delta.
   *
   * @return string
   *   Base context.
   */
  public function getBaseContext(EntityInterface $entity, string $field_name = '', ?int $delta = NULL) {

    // Make base of context.
    $base_context = $entity->getEntityType()->id() . ':' . $entity->bundle() . ':' . $entity->id() . '.';
    if ($field_name != '') {
      $base_context .= $field_name;
    }
    if ($delta !== NULL) {
      $base_context .= '.' . (string) $delta;
    }

    return $base_context;
  }

  /**
   * Recover crops from an incomplete context.
   *
   * @param string $base_context
   *   Incomplete context.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   Crops.
   */
  public function getContextualCropsFromBaseContext(string $base_context) {

    // Find and delete each crop associated with this base context.
    $crop_ids = $this->entityTypeManager->getStorage('crop')->getQuery()
      ->condition('context', $base_context . '%', 'LIKE')
      ->accessCheck(FALSE)
      ->execute();

    return $this->entityTypeManager->getStorage('crop')->loadMultiple($crop_ids);

  }

}
