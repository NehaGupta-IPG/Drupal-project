<?php

namespace Drupal\Core\File\MimeType;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Makes possible to guess the MIME type of file without its extension.
 */
class ContentBasedMimeTypeGuesser implements MimeTypeGuesserInterface {

  /**
   * {@inheritdoc}
   */
  public function guessMimeType($path): ?string {
    $part_extension = (new \SplFileInfo($path))->getExtension();

    // If the file is a temporary file and with no extension like /tmp/foobar
    if ($part_extension === '') {
      if (!is_file($path) || !is_readable($path)) {
        return NULL;
      }

      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      return $finfo->file($path);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isGuesserSupported(): bool {
    return extension_loaded('fileinfo');
  }

}
