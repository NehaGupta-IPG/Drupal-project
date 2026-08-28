<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\File\Exception\FileExistsException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\Exception\InvalidStreamWrapperException;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\UploadedFilesExtractor;
use Drupal\Core\Lock\LockAcquiringException;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Helper class for multiple form file uploads.
 *
 * This class provides API-level methods for uploading multiple files from a
 * form. It does not interact with the Form API or the Messenger service; it
 * returns errors as data on the FormFileUploadResult it returns.
 *
 * For Form API elements or user-facing messages, use
 * \Drupal\file\Upload\ManagedFileElementHelper instead.
 *
 * @see \Drupal\file\Upload\ManagedFileElementHelper
 */
class FormFileUploader {
  use StringTranslationTrait;

  public function __construct(
    protected readonly UploadedFilesExtractor $uploadedFilesExtractor,
    protected readonly FileUploadHandlerInterface $fileUploadedHandler,
    #[Autowire(service: 'cache.memory')]
    protected MemoryCacheInterface $memoryCache,
    #[AutowireServiceClosure('logger.channel.file')]
    protected readonly \Closure $logger,
  ) {}

  /**
   * Saves file uploads to a new location.
   *
   * The files will be added to the {file_managed} table as temporary files.
   * Temporary files are periodically cleaned. Use the 'file.usage' service to
   * register the usage of the file which will automatically mark it as
   * permanent.
   *
   * This method does not report errors to the user. It is the caller's
   * responsibility to inspect FormFileUploadResult::getErrors() and display
   * them appropriately.
   *
   * @param string $formFieldName
   *   A string that is the associative array key of the upload form element in
   *   the form array.
   * @param array $validators
   *   (optional) An associative array of Validation Constraint plugins used to
   *   validate the file.
   *   If the array is empty, 'FileExtension' will be used by default with a
   *   safe list of extensions, as follows: "jpg jpeg gif png txt doc xls pdf
   *   ppt pps odt ods odp". To allow all extensions, you must explicitly set
   *   this array to ['FileExtension' => []]. (Beware: this is not safe and
   *   should only be allowed for trusted users, if at all.)
   * @param string|false $destination
   *   (optional) A string containing the URI that the file should be copied
   *   to. This must be a stream wrapper URI. If this value is omitted or set
   *   to FALSE, Drupal's temporary files scheme will be used ("temporary://").
   * @param \Drupal\Core\File\FileExists $fileExists
   *   (optional) The replace behavior when the destination file already
   *   exists.
   *
   * @return \Drupal\file\Upload\FormFileUploadResult|null
   *   The result of the upload, or NULL if no file was uploaded.
   *
   * @see ManagedFileElementHelper::saveFileUploads()
   */
  public function saveFormUploadedFiles(
    string $formFieldName,
    array $validators = [],
    string|bool $destination = FALSE,
    FileExists $fileExists = FileExists::Rename,
  ): ?FormFileUploadResult {
    $cid = 'file:uploads:' . $formFieldName;

    $uploadedFiles = $this->uploadedFilesExtractor->extractUploadedFiles($formFieldName);
    if (empty($uploadedFiles)) {
      return NULL;
    }

    // Return cached objects without processing since the file will have
    // already been processed and the paths in $_FILES will be invalid.
    if ($cached = $this->memoryCache->get($cid)) {
      return $cached->data;
    }

    if ($destination === FALSE) {
      $destination = 'temporary://';
    }

    $results = [];
    foreach ($uploadedFiles as $i => $uploadedFile) {
      try {
        $form_uploaded_file = new FormUploadedFile($uploadedFile);
        $result = $this->fileUploadedHandler->handleFileUpload($form_uploaded_file, $validators, $destination, $fileExists);
        if ($result->hasViolations()) {
          $violationMessages = [];
          foreach ($result->getViolations() as $violation) {
            $violationMessages[] = (string) $violation->getMessage();
          }
          // Violation messages are TranslatableMarkup (see DrupalTranslator),
          // already escaped, so combine as markup to avoid double-escaping.
          $results[$i] = UploadedFileResult::failure($this->t('The specified file %name could not be uploaded: @errors', [
            '%name' => $uploadedFile->getClientOriginalName(),
            '@errors' => Markup::create(implode(' ', $violationMessages)),
          ]));
          continue;
        }
        if ($result->hasErrors()) {
          $error = $result->getErrors()[0];
          $results[$i] = UploadedFileResult::failure($error instanceof FileExistsException
            ? $this->t('Destination file "%file" exists', ['%file' => $destination . $uploadedFile->getFilename()])
            : $this->t('The file %filename could not be uploaded because the name is invalid.', ['%filename' => $uploadedFile->getClientOriginalName()]));
          continue;
        }
        $file = $result->getFile();
        // Log security renames to help detect potential malicious uploads.
        if ($result->isSecurityRename()) {
          ($this->logger)()->notice('For security reasons, the uploaded file %original_filename has been renamed to %filename.', [
            '%original_filename' => $result->getOriginalFilename(),
            '%filename' => $file->getFilename(),
          ]);
        }
        $results[$i] = UploadedFileResult::success($file);
      }
      catch (InvalidStreamWrapperException) {
        $results[$i] = UploadedFileResult::failure($this->t('The file could not be uploaded because the destination "%destination" is invalid.', ['%destination' => $destination]));
      }
      catch (FileWriteException) {
        ($this->logger)()->notice('Upload error. Could not move uploaded file %file to destination %destination.', [
          '%file' => $uploadedFile->getClientOriginalName(),
          '%destination' => $destination . '/' . $uploadedFile->getClientOriginalName(),
        ]);
        $results[$i] = UploadedFileResult::failure($this->t('File upload error. Could not move uploaded file.'));
      }
      catch (LockAcquiringException) {
        $results[$i] = UploadedFileResult::failure($this->t('File already locked for writing.'));
      }
    }

    $result = new FormFileUploadResult($results);

    // Cache the files for the duration of the request. Errors are not
    // cached, since they have already been returned to the caller once.
    $this->memoryCache->set($cid, $result->withoutErrors(), Cache::PERMANENT);

    return $result;
  }

}
