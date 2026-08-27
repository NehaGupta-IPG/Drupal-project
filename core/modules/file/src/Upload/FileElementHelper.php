<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Helper class for ManagedFile element form file uploads.
 *
 * This class handles the Form API and user-facing messages.
 *
 * For API-level form file uploading, use
 * \Drupal\file\Upload\FormFileUploadHandler instead.
 *
 * @see \Drupal\file\Upload\FormFileUploader
 * @see \Drupal\file\Element\ManagedFile::valueCallback()
 */
class FileElementHelper {
  use StringTranslationTrait;

  public function __construct(
    protected readonly MessengerInterface $messenger,
    protected readonly RendererInterface $renderer,
    protected readonly FormFileUploader $formFileUploader,
  ) {}

  /**
   * Saves form file uploads.
   *
   * The files will be added to the {file_managed} table as temporary files.
   * Temporary files are periodically cleaned. Use the 'file.usage' service to
   * register the usage of the file which will automatically mark it as permanent.
   *
   * @param array $element
   *   The FAPI element whose values are being saved.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The current state of the form.
   * @param null|int $delta
   *   (optional) The delta of the file to return the file entity.
   *   Defaults to NULL.
   * @param \Drupal\Core\File\FileExists $fileExists
   *   (optional) The replace behavior when the destination file already exists.
   *
   * @return array|\Drupal\file\FileInterface|null|false
   *   An array of file entities or a single file entity if $delta != NULL. Each
   *   array element contains the file entity if the upload succeeded or FALSE if
   *   there was an error. Function returns NULL if no file was uploaded.
   *
   * @internal
   *
   * @see https://www.drupal.org/project/drupal/issues/3069020
   * @see https://www.drupal.org/project/drupal/issues/2482783
   */
  public function saveFileUploads(array $element, FormStateInterface $formState, $delta = NULL, FileExists $fileExists = FileExists::Rename) {
    // Get all errors set before calling this method. This will also clear them
    // from the messenger service.
    $errorsBefore = $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);

    $uploadLocation = $element['#upload_location'] ?? FALSE;
    $uploadName = implode('_', $element['#parents']);
    $uploadValidators = $element['#upload_validators'] ?? [];

    $result = $this->formFileUploader->saveFormUploadedFiles($uploadName, $uploadValidators, $uploadLocation, $delta, $fileExists);

    // Get new errors that are generated while trying to save the upload. This
    // will also clear them from the messenger service.
    $errors_new = $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
    if (!empty($errors_new)) {

      if (count($errors_new) > 1) {
        // Render multiple errors into a single message.
        // This is needed because only one error per element is supported.
        $render_array = [
          'error' => [
            '#markup' => $this->t('One or more files could not be uploaded.'),
          ],
          'item_list' => [
            '#theme' => 'item_list',
            '#items' => $errors_new,
          ],
        ];
        $error_message = $this->renderer->renderInIsolation($render_array);
      }
      else {
        $error_message = reset($errors_new);
      }

      $formState->setError($element, $error_message);
    }

    // Ensure that errors set prior to calling this method are still shown to the
    // user.
    if (!empty($errorsBefore)) {
      foreach ($errorsBefore as $error) {
        $this->messenger->addError($error);
      }
    }

    return $result;
  }

}