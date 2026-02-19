<?php

namespace Drupal\Core\Config;

/**
 * Interface for a storage manager.
 */
interface StorageManagerInterface {

  /**
   * Get the config storage.
   *
   * @return StorageInterface
   *   The config storage.
   *
   * @throws StorageTransformerException
   *   Thrown when the lock could not be acquired.
   */
  public function getStorage();

}
