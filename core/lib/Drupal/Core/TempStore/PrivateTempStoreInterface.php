<?php

namespace Drupal\Core\TempStore;

/**
 * An interface for private temporary data stores.
 *
 * A private temporary data store can be used to make temporary, non-cache data
 * available across requests.
 *
 * A private temporary data store is different from a cache, because the data in
 * it is not yet saved permanently and so it cannot be rebuilt. Typically, a
 * private temporary data store might be used to store work in progress that is
 * later saved permanently elsewhere, e.g. autosave data, multistep forms, or
 * in-progress changes to complex configuration that are not ready to be saved.
 *
 * A private temporary data store differs from a shared temporary data store in
 * that all keys are ensured to be unique for a particular owner and owners can
 * never share data. If you want to be able to share data between users or use
 * it for locking, use SharedTempStoreInterface.
 *
 * @see \Drupal\Core\TempStore\SharedTempStoreInterface
 */
interface PrivateTempStoreInterface {

  /**
   * Retrieves a value from the store for a given key.
   *
   * @param string $key
   *   The key of the data to retrieve.
   *
   * @return mixed
   *   The data associated with the key, or NULL if the key does not exist.
   */
  public function get($key);

  /**
   * Stores a particular key/value pair in the store.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  public function set($key, $value);

  /**
   * Returns the metadata associated with a particular key/value pair.
   *
   * @param string $key
   *   The key of the data to store.
   *
   * @return \Drupal\Core\TempStore\Lock|null
   *   An object with the owner and updated time if the key has a value, or
   *   NULL otherwise.
   */
  public function getMetadata($key);

  /**
   * Deletes data from the store for a given key and releases the lock on it.
   *
   * @param string $key
   *   The key of the data to delete.
   *
   * @return bool
   *   TRUE if the object was deleted or does not exist, FALSE if it exists but
   *   is not owned by $this->owner.
   */
  public function delete($key);

}
