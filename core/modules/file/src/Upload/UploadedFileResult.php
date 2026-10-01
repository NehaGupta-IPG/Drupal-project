<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\Component\Render\MarkupInterface;
use Drupal\file\FileInterface;

/**
 * Value object for the result of saving a single uploaded file from a form.
 */
final class UploadedFileResult {

  private function __construct(
    private readonly FileInterface|false $file,
    private readonly string|MarkupInterface|null $error,
    private readonly array $errorDetails = [],
  ) {}

  /**
   * Creates a result for a successful upload.
   *
   * @param \Drupal\file\FileInterface $file
   *   The uploaded file.
   *
   * @return self
   *   The result.
   */
  public static function success(FileInterface $file): self {
    return new self($file, NULL);
  }

  /**
   * Creates a result for a failed upload.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface|null $error
   *   The error message, or NULL to suppress it (used when returning a
   *   previously-reported failure from cache).
   * @param array<string|\Stringable> $errorDetails
   *   (optional) Detailed messages explaining the error, for example
   *   validation violation messages.
   *
   * @return self
   *   The result.
   */
  public static function failure(string|MarkupInterface|null $error, array $errorDetails = []): self {
    return new self(FALSE, $error, $errorDetails);
  }

  /**
   * Gets the uploaded file.
   *
   * @return \Drupal\file\FileInterface|false
   *   The file entity, or FALSE if the upload failed.
   */
  public function getFile(): FileInterface|false {
    return $this->file;
  }

  /**
   * Gets the error message.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   The error message, or NULL if the upload succeeded or the error has
   *   been suppressed.
   */
  public function getError(): string|MarkupInterface|null {
    return $this->error;
  }

  /**
   * Gets the detailed messages explaining the error.
   *
   * @return array<string|\Stringable>
   *   The detail messages, for example validation violation messages. Empty
   *   if there are none or the error has been suppressed.
   */
  public function getErrorDetails(): array {
    return $this->errorDetails;
  }

  /**
   * Returns whether the upload succeeded.
   *
   * @return bool
   *   TRUE if the upload succeeded, FALSE otherwise.
   */
  public function isSuccessful(): bool {
    return $this->file !== FALSE;
  }

}
