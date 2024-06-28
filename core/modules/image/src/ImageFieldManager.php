<?php

namespace Drupal\image;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;

/**
 * Provides a service for managing image fields.
 */
class ImageFieldManager {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The initialized array.
   */
  private ?array $cachedDefaults;

  /**
   * Construct a new image field manager.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(CacheBackendInterface $cache, EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository) {
    $this->cache = $cache;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRepository = $entityRepository;
    $this->cachedDefaults = NULL;
  }

  /**
   * Map default values for image fields, and those fields' configuration IDs.
   *
   * This is used in image_file_download() to determine whether to grant access to
   * an image stored in the private file storage.
   *
   * @return array
   *   An associative array, where the keys are image file URIs, and the values
   *   are arrays of field configuration IDs which use that image file as their
   *   default image. For example,
   *
   * @code [
   *     'private://default_images/astronaut.jpg' => [
   *       'node.article.field_image',
   *       'user.user.field_portrait',
   *     ],
   *   ]
   * @code
   */
  public function getDefaultImageFields(): array {
    $cid = 'image:default_images';
    if (!isset($this->cachedDefaults)) {
      $cache = $this->cache->get($cid);
      if ($cache) {
        $this->cachedDefaults = $cache->data;
      }
      else {
        // Save a map of all default image UUIDs and their corresponding field
        // configuration IDs for quick lookup.
        $defaults = [];
        $fields = $this->entityTypeManager
          ->getStorage('entity_field.manager')
          ->loadMultiple();

        foreach ($fields as $field) {
          if ($field->getType() === 'image') {
            // Check if there is a default image in the field config.
            $field_uuid = $field->getSetting('default_image')['uuid'];
            if ($field_uuid) {
              $file = $this->entityRepository->loadEntityByUuid('file', $field_uuid);
              if ($file instanceof FileInterface) {
                // A default image could be used by multiple field configs.
                $defaults[$file->getFileUri()][] = $field->get('id');
              }
            }

            // Field storage config can also have a default image.
            $storage_uuid = $field->getFieldStorageDefinition()->getSetting('default_image')['uuid'];
            if ($storage_uuid) {
              $file = $this->entityRepository->loadEntityByUuid('file', $storage_uuid);
              if ($file instanceof FileInterface) {
                // Use the field config id since that is what we'll be using to
                // check access in image_file_download().
                $defaults[$file->getFileUri()][] = $field->get('id');
              }
            }
          }
        }

        // Cache the default image list.
        $this->cache
          ->set($cid, $defaults, CacheBackendInterface::CACHE_PERMANENT, [
            'image_default_images',
            'entity_field_info',
          ]);
        $this->cachedDefaults = $defaults;
      }
    }
    return $this->cachedDefaults;
  }

}
