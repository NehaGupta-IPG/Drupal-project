<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\file\FileInterface;

/**
 * Value object for the result of saving multiple form file uploads.
 */
final class FormFileUploadResult {

  /**
   * Constructs a new FormFileUploadResult.
   *
   * @param array<int, \Drupal\file\Upload\UploadedFileResult> $results
   *   The per-delta upload results.
   */
  public function __construct(
    protected readonly array $results,
  ) {}

  /**
   * Gets all the uploaded files, keyed by delta.
   *
   * @return array<int, \Drupal\file\FileInterface|false>
   *   The uploaded files.
   */
  public function getFiles(): array {
    return array_map(static fn (UploadedFileResult $result) => $result->getFile(), $this->results);
  }

  /**
   * Gets a single uploaded file.
   *
   * @param int $delta
   *   The delta of the file to return.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file entity, or FALSE if the upload at that delta failed.
   */
  public function getFile(int $delta): FileInterface|false {
    return isset($this->results[$delta]) ? $this->results[$delta]->getFile() : FALSE;
  }

  /**
   * Gets the error messages, keyed by delta.
   *
   * @return array<int, string|\Drupal\Component\Render\MarkupInterface>
   *   The error messages.
   */
  public function getErrors(): array {
    $errors = [];
    foreach ($this->results as $delta => $result) {
      $error = $result->getError();
      if ($error !== NULL) {
        $errors[$delta] = $error;
      }
    }
    return $errors;
  }

  /**
   * Gets the detailed error messages, keyed by delta.
   *
   * Only deltas whose error has detail messages are included. Callers that
   * display errors should show these with the matching message from
   * ::getErrors(), for example as an item list.
   *
   * @return array<int, array<string|\Stringable>>
   *   The detail messages, keyed by delta.
   */
  public function getErrorDetails(): array {
    $details = [];
    foreach ($this->results as $delta => $result) {
      if ($result->getError() !== NULL && $result->getErrorDetails()) {
        $details[$delta] = $result->getErrorDetails();
      }
    }
    return $details;
  }

  /**
   * Returns whether any of the uploads failed with a reported error.
   *
   * @return bool
   *   TRUE if there are errors, FALSE otherwise.
   */
  public function hasErrors(): bool {
    return !empty($this->getErrors());
  }

  /**
   * Returns a copy of this result with all error messages suppressed.
   *
   * Used when caching a result that has already been reported to the caller
   * once, so a subsequent cache hit does not report the same errors again.
   *
   * @return self
   *   The new result.
   */
  public function withoutErrors(): self {
    return new self(array_map(
      static fn (UploadedFileResult $result) => $result->isSuccessful() ? $result : UploadedFileResult::failure(NULL),
      $this->results,
    ));
  }

}
