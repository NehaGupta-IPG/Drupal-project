<?php

namespace Drupal\Core\TempStore;

/**
 * An interface for shared temporary data stores.
 *
 * A shared temporary data store can be used to make temporary, non-cache data
 * available across requests.
 *
 * A shared temporary data store is different from a cache, because the data in
 * it is not yet saved permanently and so it cannot be rebuilt. Typically, a
 * shared temporary data store might be used to store work in progress that is
 * later saved permanently elsewhere, e.g. autosave data, multistep forms, or
 * in-progress changes to complex configuration that are not ready to be saved.
 *
 * Each shared temporary data store belongs to a particular owner (e.g. a user,
 * session, or process). Multiple owners may use the same key/value, and the
 * owner should be stored along with the key/value pair.
 *
 * Every key is unique, so a hared temporary data store can check whether a
 * particular key is already set by a different owner. This is useful for
 * informing one owner that the data is already in use by another; for example,
 * to let one user know that another user is in the process of editing certain
 * data, or even to restrict other users from editing it at the same time. It is
 * the responsibility of the implementation to decide when and whether one owner
 * can use or update another owner's data.
 *
 * If you want to be able to ensure that the data belongs to the current user,
 * use PrivateTempStoreInterface.
 *
 * @see \Drupal\Core\TempStore\PrivateTempStoreInterface
 */
interface SharedTempStoreInterface {

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
   * Retrieves a value from the store for a given key.
   *
   * Only returns the value if the value is owned by the owner of the store.
   *
   * @param string $key
   *   The key of the data to retrieve.
   *
   * @return mixed
   *   The data associated with the key, or NULL if the key does not exist.
   */
  public function getIfOwner($key);

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
   * Stores a particular key/value pair in the store.
   *
   * Only stores the given key/value pair if it does not exist yet or is owned
   * by the owner of the store.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   *
   * @return bool
   *   TRUE if the data was set, or FALSE if it already exists and is not owned
   *   by the owner of the TempStore.
   */
  public function setIfOwner($key, $value);

  /**
   * Stores a particular key/value pair only if the key doesn't already exist.
   *
   * @param string $key
   *   The key of the data to check and store.
   * @param mixed $value
   *   The data to store.
   *
   * @return bool
   *   TRUE if the data was set, or FALSE if it already existed.
   */
  public function setIfNotExists($key, $value);

  /**
   * Deletes data from the store for a given key and releases the lock on it.
   *
   * @param string $key
   *   The key of the data to delete.
   */
  public function delete($key);

  /**
   * Deletes data from the store for a given key and releases the lock on it.
   *
   * Only delete the given key if it is owned by the owner of the store.
   *
   * @param string $key
   *   The key of the data to delete.
   *
   * @return bool
   *   TRUE if the object was deleted or does not exist, FALSE if it exists but
   *   is not owned by the owner of the store.
   */
  public function deleteIfOwner($key);

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

}
