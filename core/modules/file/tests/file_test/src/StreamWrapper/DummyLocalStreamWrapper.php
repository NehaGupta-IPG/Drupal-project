<?php

declare(strict_types=1);

namespace Drupal\file_test\StreamWrapper;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\StreamWrapper\LocalStream;

/**
 * Provides a dummy local stream wrapper for testing.
 */
class DummyLocalStreamWrapper extends LocalStream {

  /**
   * {@inheritdoc}
   */
  public function getDirectoryPath() {
    return FileSystem::getOsTemporaryDirectory();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    throw new \RuntimeException('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    throw new \RuntimeException('Not implemented');
  }

  /**
   * {@inheritdoc}
   */
  public function getExternalUrl() {
    throw new \RuntimeException('Not implemented');
  }

}
