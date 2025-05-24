<?php

namespace Drupal\Core\TempStore;

/**
 * Provides an interface for a shared temp store factory.
 *
 * @see \Drupal\Core\TempStore\SharedTempStoreInterface
 */
interface SharedTempStoreFactoryInterface {

  /**
   * Creates a shared temp store for the current user or anonymous session.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   * @param mixed $owner
   *   (optional) The owner of this SharedTempStore. By default, the
   *   SharedTempStore is owned by the currently authenticated user, or by the
   *   active anonymous session if no user is logged in.
   *
   * @return \Drupal\Core\TempStore\SharedTempStoreInterface
   *   An instance of the key/value store.
   */
  public function get($collection, $owner = NULL);

}
