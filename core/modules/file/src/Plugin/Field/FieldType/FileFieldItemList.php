<?php

namespace Drupal\file\Plugin\Field\FieldType;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Represents a configurable entity file field.
 */
class FileFieldItemList extends EntityReferenceFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $this->moveFilesToSelectedUriScheme();

    $entity = $this->getEntity();

    if (!$update) {
      // Add a new usage for newly uploaded files.
      foreach ($this->referencedEntities() as $file) {
        \Drupal::service('file.usage')->add($file, 'file', $entity->getEntityTypeId(), $entity->id());
      }
    }
    else {
      // Get current target file entities and file IDs.
      $files = $this->referencedEntities();
      $ids = [];

      /** @var \Drupal\file\FileInterface $file */
      foreach ($files as $file) {
        $ids[] = $file->id();
      }

      // On new revisions, all files are considered to be a new usage and no
      // deletion of previous file usages are necessary.
      if ($entity->getRevisionId() != $entity->getOriginal()?->getRevisionId()) {
        foreach ($files as $file) {
          \Drupal::service('file.usage')->add($file, 'file', $entity->getEntityTypeId(), $entity->id());
        }
        return;
      }

      // Get the file IDs attached to the field before this update.
      $field_name = $this->getFieldDefinition()->getName();
      $original_ids = [];
      $langcode = $this->getLangcode();
      $original = $entity->getOriginal();
      if ($original->hasTranslation($langcode)) {
        foreach ($original->getTranslation($langcode)->{$field_name} as $item) {
          $original_ids[] = $item->target_id;
        }
      }

      // Decrement file usage by 1 for files that were removed from the field.
      $removed_ids = array_filter(array_diff($original_ids, $ids));
      $removed_files = \Drupal::entityTypeManager()->getStorage('file')->loadMultiple($removed_ids);
      foreach ($removed_files as $file) {
        \Drupal::service('file.usage')->delete($file, 'file', $entity->getEntityTypeId(), $entity->id());
      }

      // Add new usage entries for newly added files.
      foreach ($files as $file) {
        if (!in_array($file->id(), $original_ids)) {
          \Drupal::service('file.usage')->add($file, 'file', $entity->getEntityTypeId(), $entity->id());
        }
      }
    }
  }

  /**
   * Moves referenced files to the URI scheme selected in the file widget.
   *
   * The scheme is only applied once the entity is saved, so abandoned forms,
   * validation errors and AJAX requests leave the file in place.
   *
   * @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::process()
   */
  protected function moveFilesToSelectedUriScheme(): void {
    if (!$this->getSetting('uri_scheme_field')) {
      return;
    }

    $stream_wrapper_manager = \Drupal::service(StreamWrapperManagerInterface::class);
    $allowed_schemes = $stream_wrapper_manager->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    foreach ($this->list as $item) {
      $values = $item->getValue();
      if (!isset($values['uri_scheme'])) {
        continue;
      }
      $uri_scheme = $values['uri_scheme'];

      // Clear the selection so that a later save does not repeat the move.
      unset($values['uri_scheme']);
      $item->setValue($values, FALSE);

      $file = $item->entity;
      if (!is_string($uri_scheme) || !isset($allowed_schemes[$uri_scheme]) || !$file instanceof FileInterface) {
        continue;
      }
      $current_uri = $file->getFileUri();
      if ($uri_scheme === $stream_wrapper_manager::getScheme($current_uri) || !file_exists($current_uri)) {
        continue;
      }

      $destination = $uri_scheme . '://' . $stream_wrapper_manager::getTarget($current_uri);
      $file_system = \Drupal::service(FileSystemInterface::class);
      $directory = $file_system->dirname($destination);
      if (!$file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        continue;
      }
      try {
        \Drupal::service(FileRepositoryInterface::class)->move($file, $destination);
      }
      catch (FileException $e) {
        // Keep the file in its current location.
        \Drupal::logger('file')->error('The file %file could not be moved to %destination: @message', [
          '%file' => $current_uri,
          '%destination' => $destination,
          '@message' => $e->getMessage(),
        ]);
        \Drupal::messenger()->addError(new TranslatableMarkup('The file %filename could not be moved to the selected location.', ['%filename' => $file->getFilename()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    $entity = $this->getEntity();

    // If a translation is deleted only decrement the file usage by one. If the
    // default translation is deleted remove all file usages within this entity.
    $count = $entity->isDefaultTranslation() ? 0 : 1;
    foreach ($this->referencedEntities() as $file) {
      \Drupal::service('file.usage')->delete($file, 'file', $entity->getEntityTypeId(), $entity->id(), $count);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRevision() {
    parent::deleteRevision();
    $entity = $this->getEntity();

    // Decrement the file usage by 1.
    foreach ($this->referencedEntities() as $file) {
      \Drupal::service('file.usage')->delete($file, 'file', $entity->getEntityTypeId(), $entity->id());
    }
  }

}
