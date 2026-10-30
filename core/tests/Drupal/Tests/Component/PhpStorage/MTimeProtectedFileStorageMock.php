<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\PhpStorage;

use Drupal\Component\PhpStorage\MTimeProtectedFileStorage;

/**
 * Mock storage class for testing race condition in checkFile().
 *
 * This class extends MTimeProtectedFileStorage and overrides checkFile()
 * to use getFilemtime() method, which simulates the race condition by
 * deleting the file before calling filemtime().
 */
class MTimeProtectedFileStorageMock extends MTimeProtectedFileStorage {

  /**
   * {@inheritdoc}
   *
   * When MTimeProtectedFileStorageBase::$simulateFilemtimeFailure is TRUE,
   * this method deletes the file before calling filemtime() to simulate
   * the race condition where the file is deleted before the filemtime() call.
   */
  protected function getFilemtime(string $filename): int|false {
    if (MTimeProtectedFileStorageBase::$simulateFilemtimeFailure && file_exists($filename)) {
      // Delete the file to simulate race condition.
      $directory = dirname($filename);
      @chmod($directory, 0700);
      @chmod($filename, 0700);
      @unlink($filename);
    }
    return parent::getFilemtime($filename);
  }

}
