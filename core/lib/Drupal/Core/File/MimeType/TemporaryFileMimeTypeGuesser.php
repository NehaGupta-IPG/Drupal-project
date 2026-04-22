<?php

namespace Drupal\Core\File\MimeType;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Makes possible to guess the MIME type of file without its extension.
 */
class TemporaryFileMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * {@inheritdoc}
   */
  public function guessMimeType($path): ?string {
    $part_extension = (new \SplFileInfo($path))->getExtension();

    // If the file is a temporary file and with no extension like /tmp/ksupn
    if ($part_extension === '') {
      static $finfo;
      $finfo ??= new \finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($path);
      return ($mime !== FALSE) ? $mime : NULL;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return function_exists('finfo_open');
  }

}
