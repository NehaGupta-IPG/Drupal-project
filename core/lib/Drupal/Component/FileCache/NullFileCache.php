<?php

namespace Drupal\Component\FileCache;

/**
 * Null implementation for the file cache.
 */
class NullFileCache implements FileCacheInterface {

  /**
   * {@inheritdoc}
   */
  public function get($filepath) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $filepaths) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function set($filepath, $data) {
  }

  /**
   * {@inheritdoc}
   */
  public function delete($filepath) {
  }

}
