<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\Core\File\FileExists;

/**
 * Handles validating and creating file entities from file uploads.
 */
interface FileUploadHandlerInterface {

  /**
   * Creates a file from an upload.
   *
   * @param \Drupal\file\Upload\UploadedFileInterface $uploadedFile
   *   The uploaded file object.
   * @param array $validators
   *   The validators to run against the uploaded file.
   * @param string $destination
   *   The destination directory.
   * @param \Drupal\Core\File\FileExists $fileExists
   *   The behavior when the destination file already exists.
   *
   * @return \Drupal\file\Upload\FileUploadResult
   *   The result of the upload. FileUploadResult::getErrors() returns any
   *   FileExistsException or FileException encountered while resolving the
   *   destination filename.
   *
   * @throws \Drupal\Core\File\Exception\InvalidStreamWrapperException
   *   Thrown when the destination scheme is invalid.
   * @throws \Drupal\Core\File\Exception\FileWriteException
   *   Thrown when there is an error moving the file.
   * @throws \Drupal\Core\Lock\LockAcquiringException
   *   Thrown when a lock cannot be acquired.
   */
  public function handleFileUpload(UploadedFileInterface $uploadedFile, array $validators = [], string $destination = 'temporary://', FileExists $fileExists = FileExists::Replace): FileUploadResult;

}
