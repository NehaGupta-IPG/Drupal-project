<?php

namespace Drupal\Component\FileCache;

/**
 * Null implementation for the file cache.
 */
class NullFileCache implements FileCacheInterface {

  public function __construct($prefix, $collection, $cache_backend_class = NULL, array $cache_backend_configuration = []) {
  }

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
