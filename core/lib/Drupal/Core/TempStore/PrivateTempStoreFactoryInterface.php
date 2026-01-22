<?php

namespace Drupal\Core\TempStore;

/**
 * Provides an interface for a private temp store factory.
 *
 * @see \Drupal\Core\TempStore\PrivateTempStoreInterface
 */
interface PrivateTempStoreFactoryInterface {

  /**
   * Creates the private temp store.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStoreInterface
   *   An instance of the key/value store.
   */
  public function get($collection);

}
