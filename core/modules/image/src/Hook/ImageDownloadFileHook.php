<?php

declare(strict_types=1);

namespace Drupal\image\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Implements hook_file_download().
 */
#[Hook('file_download')]
class ImageDownloadFileHook {

  /**
   * Cache for private default images.
   *
   * @var array<string, \Drupal\Core\Field\FieldDefinitionInterface[]>
   */
  protected array $cachedPrivateDefaultImages;

  public function __construct(
    #[Autowire(service: 'cache.default')]
    protected readonly CacheBackendInterface $cache,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly AccountInterface $currentUser,
    protected readonly ImageFactory $imageFactory,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_file_download().
   */
  public function __invoke($uri): array|int|null {
    $path = StreamWrapperManager::getTarget($uri);
    // Private file access for image style derivatives.
    if (str_starts_with($path, 'styles/')) {
      $args = explode('/', $path);
      // Discard "styles", style name, and scheme from the path
      $args = array_slice($args, 3);
      // Then the remaining parts are the path to the image.
      $original_uri = StreamWrapperManager::getScheme($uri) . '://' . implode('/', $args);
      // Check that the file exists and is an image.
      $image = $this->imageFactory->get($uri);
      if ($image->isValid()) {
        // If the image style converted the extension, it has been added to the
        // original file, resulting in filenames like image.png.jpeg. So to find
        // the actual source image, we remove the extension and check if that
        // image exists.
        if (!file_exists($original_uri)) {
          $converted_original_uri = ImageStyleDownloadController::getUriWithoutConvertedExtension($original_uri);
          if ($converted_original_uri !== $original_uri && file_exists($converted_original_uri)) {
            // The converted file does exist, use it as the source.
            $original_uri = $converted_original_uri;
          }
        }
        // Check the permissions of the original to grant access to this image.
        $headers = $this->moduleHandler->invokeAll('file_download', [$original_uri]);
        // Confirm there's at least one module granting access and none denying access.
        if (!empty($headers) && !in_array(-1, $headers)) {
          return [
            // Send headers describing the image's size, and MIME-type.
            'Content-Type' => $image->getMimeType(),
            'Content-Length' => $image->getFileSize(),
          ];
        }
      }
      return -1;
    }
    // If it is the sample image we need to grant access.
    $samplePath = $this->configFactory->get('image.settings')->get('preview_image');
    if ($path === $samplePath) {
      $image = $this->imageFactory->get($samplePath);
      return [
        // Send headers describing the image's size, and MIME-type.
        'Content-Type' => $image->getMimeType(),
        'Content-Length' => $image->getFileSize(),
      ];
    }

    // Private file access for image fields' default images. Default images are
    // displayed as a fallback when an image is not uploaded to an image field.
    if (str_starts_with($path, ImageItem::DEFAULT_IMAGE_DIRECTORY . DIRECTORY_SEPARATOR)) {
      $image = $this->imageFactory->get($uri);
      if ($image->isValid()) {
        $private_default_images = $this->getPrivateDefaultImages();
        if (isset($private_default_images[$uri])) {
          foreach ($private_default_images[$uri] as $field_definition) {
            $access_control_handler = $this->entityTypeManager->getAccessControlHandler($field_definition->getTargetEntityTypeId());
            // As long as the user has view access to at least one of the fields,
            // that uses this image as a default, we can exit this foreach loop,
            // and grant access.
            if ($access_control_handler->fieldAccess('view', $field_definition)) {
              return [
                // Send headers describing the image's size, and MIME-type.
                'Content-Type' => $image->getMimeType(),
                'Content-Length' => $image->getFileSize(),
                // By not explicitly setting them here, this uses normal Drupal
                // Expires, Cache-Control and ETag headers to prevent proxy or
                // browser caching of private images.
              ];
            }
          }
        }
      }
      return -1;
    }

    return NULL;
  }

  /**
   * Returns the mapping of private default images to field definitions.
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface[]>
   *   An associative array where keys are private image URIs and values are
   *   arrays of field definitions that reference these images as defaults.
   */
  protected function getPrivateDefaultImages(): array {
    if (!isset($this->cachedPrivateDefaultImages)) {
      $cid = 'image:default_images';
      if ($cache = $this->cache->get($cid)) {
        $this->cachedPrivateDefaultImages = $cache->data;
      }
      else {
        // Save a map of all default image UUIDs and their corresponding field
        // definitions for quick lookup.
        $private_default_images = [];
        $field_map = $this->entityFieldManager->getFieldMapByFieldType('image');
        $cache_tags = [
          'image_default_images',
          'entity_field_info',
        ];
        foreach ($field_map as $entity_type_id => $fields) {
          // Do not filter field storages by uri_scheme, as file entities may
          // change regardless of the field storage configuration.
          $field_storages = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
          foreach ($fields as $field_name => $field_info) {
            // First, check if the default image is set on the field storage.
            $uri_from_storage = NULL;
            $file_uuid = $field_storages[$field_name]->getSetting('default_image')['uuid'];
            if ($file_uuid && $file = $this->entityRepository->loadEntityByUuid('file', $file_uuid)) {
              /** @var \Drupal\file\FileInterface $file */
              $uri_from_storage = $file->getFileUri();
              $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());
            }

            foreach ($field_info['bundles'] as $bundle) {
              $field_definition = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle)[$field_name];
              $default_uri = $uri_from_storage;
              $file_uuid = $field_definition->getSetting('default_image')['uuid'];
              // If the default image is overridden in the field definition, use
              // that instead of the one set on the field storage.
              if ($file_uuid && $file = $this->entityRepository->loadEntityByUuid('file', $file_uuid)) {
                /** @var \Drupal\file\FileInterface $file */
                $default_uri = $file->getFileUri();
                $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());
              }
              // Finally, if a private default image URI was found,
              // add it to the list.
              if ($default_uri && StreamWrapperManager::getScheme($default_uri) === 'private') {
                $private_default_images[$default_uri][] = $field_definition;
              }
            }
          }
        }
        // Cache the default image list.
        $this->cachedPrivateDefaultImages = $private_default_images;
        $this->cache->set($cid, $private_default_images, CacheBackendInterface::CACHE_PERMANENT, $cache_tags);
      }
    }
    return $this->cachedPrivateDefaultImages;
  }

}
