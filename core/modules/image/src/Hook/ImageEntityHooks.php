<?php

declare(strict_types=1);

namespace Drupal\image\Hook;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\image\ImageDerivativeUtilities;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Entity hook implementations for image.
 */
class ImageEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ImageFactory $imageFactory,
    protected readonly EntityRepositoryInterface $entityRepository,
    protected readonly FileUsageInterface $fileUsage,
    protected readonly FileRepositoryInterface $fileRepository,
    protected readonly FileSystemInterface $fileSystem,
    #[AutowireServiceClosure(ImageDerivativeUtilities::class)]
    protected readonly \Closure $imageDerivativeUtilitiesClosure,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_predelete() for file entities.
   */
  #[Hook('file_predelete')]
  public function filePredelete(FileInterface $file): void {
    $imageDerivativeUtilities = ($this->imageDerivativeUtilitiesClosure)();
    // Delete any image derivatives of this image.
    $imageDerivativeUtilities->pathFlush($file->getFileUri());
  }

  /**
   * Implements hook_entity_presave().
   *
   * Transforms default image of image field from array into single value at
   * save.
   */
  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    // Get the default image settings, return if not saving an image field
    // storage or image field entity.
    $default_image = [];
    if (($entity instanceof FieldStorageConfigInterface || $entity instanceof FieldConfigInterface) && $entity->getType() == 'image') {
      $default_image = $entity->getSetting('default_image');
    }
    else {
      return;
    }
    if ($entity->isSyncing()) {
      return;
    }
    $uuid = $default_image['uuid'];
    if ($uuid) {
      $original_uuid = $entity->getOriginal()?->getSetting('default_image')['uuid'];
      if ($uuid != $original_uuid) {
        $file = $this->entityRepository->loadEntityByUuid('file', $uuid);
        if ($file) {
          $image = $this->imageFactory->get($file->getFileUri());
          $default_image['width'] = $image->getWidth();
          $default_image['height'] = $image->getHeight();
        }
        else {
          $default_image['uuid'] = NULL;
        }
      }
    }
    // Both FieldStorageConfigInterface and FieldConfigInterface have a
    // setSetting() method.
    $entity->setSetting('default_image', $default_image);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_storage_config'.
   */
  #[Hook('field_storage_config_update')]
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->getType() != 'image') {
      // Only act on image fields.
      return;
    }
    $prior_field_storage = $field_storage->getOriginal();
    // The value of a managed_file element can be an array if #extended == TRUE.
    $uuid_new = $field_storage->getSetting('default_image')['uuid'];
    $uuid_old = $prior_field_storage->getSetting('default_image')['uuid'];
    $file_new = $uuid_new ? $this->entityRepository->loadEntityByUuid('file', $uuid_new) : FALSE;
    if ($uuid_new != $uuid_old) {
      // Is there a new file?
      if ($file_new) {
        $file_new->setPermanent();
        $file_new->save();
        $this->fileUsage->add($file_new, 'image', 'default_image', $field_storage->uuid());
      }
      // Is there an old file?
      if ($uuid_old && ($file_old = $this->entityRepository->loadEntityByUuid('file', $uuid_old))) {
        $this->fileUsage->delete($file_old, 'image', 'default_image', $field_storage->uuid());
      }
    }
    // If the upload destination changed, then move the file.
    if ($file_new && StreamWrapperManager::getScheme($file_new->getFileUri()) != $field_storage->getSetting('uri_scheme')) {
      $directory = $field_storage->getSetting('uri_scheme') . '://default_images/';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileRepository->move($file_new, $directory . $file_new->getFilename());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_config'.
   */
  #[Hook('field_config_update')]
  public function fieldConfigUpdate(FieldConfigInterface $field): void {
    $field_storage = $field->getFieldStorageDefinition();
    if ($field_storage->getType() != 'image') {
      // Only act on image fields.
      return;
    }
    $prior_instance = $field->getOriginal();
    $uuid_new = $field->getSetting('default_image')['uuid'];
    $uuid_old = $prior_instance->getSetting('default_image')['uuid'];
    // If the old and new files do not match, update the default accordingly.
    $file_new = $uuid_new ? $this->entityRepository->loadEntityByUuid('file', $uuid_new) : FALSE;
    if ($uuid_new != $uuid_old) {
      // Save the new file, if present.
      if ($file_new) {
        $file_new->setPermanent();
        $file_new->save();
        $this->fileUsage->add($file_new, 'image', 'default_image', $field->uuid());
      }
      // Delete the old file, if present.
      if ($uuid_old && ($file_old = $this->entityRepository->loadEntityByUuid('file', $uuid_old))) {
        $this->fileUsage->delete($file_old, 'image', 'default_image', $field->uuid());
      }
    }
    // If the upload destination changed, then move the file.
    if ($file_new && StreamWrapperManager::getScheme($file_new->getFileUri()) != $field_storage->getSetting('uri_scheme')) {
      $directory = $field_storage->getSetting('uri_scheme') . '://default_images/';
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileRepository->move($file_new, $directory . $file_new->getFilename());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for 'field_storage_config'.
   */
  #[Hook('field_storage_config_delete')]
  public function fieldStorageConfigDelete(FieldStorageConfigInterface $field): void {
    if ($field->getType() != 'image') {
      // Only act on image fields.
      return;
    }
    // The value of a managed_file element can be an array if #extended == TRUE.
    $uuid = $field->getSetting('default_image')['uuid'];
    if ($uuid && ($file = $this->entityRepository->loadEntityByUuid('file', $uuid))) {
      $this->fileUsage->delete($file, 'image', 'default_image', $field->uuid());
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for 'field_config'.
   */
  #[Hook('field_config_delete')]
  public function fieldConfigDelete(FieldConfigInterface $field): void {
    $field_storage = $field->getFieldStorageDefinition();
    if ($field_storage->getType() != 'image') {
      // Only act on image fields.
      return;
    }
    // The value of a managed_file element can be an array if #extended == TRUE.
    $uuid = $field->getSetting('default_image')['uuid'];
    // Remove the default image when the instance is deleted.
    if ($uuid && ($file = $this->entityRepository->loadEntityByUuid('file', $uuid))) {
      $this->fileUsage->delete($file, 'image', 'default_image', $field->uuid());
    }
  }

}
