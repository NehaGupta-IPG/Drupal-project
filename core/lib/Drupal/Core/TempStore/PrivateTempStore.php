<?php

namespace Drupal\Core\TempStore;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and retrieves private temporary data for a given owner.
 *
 * The data for the PrivateTempStore is stored in one key/value collection.
 * PrivateTempStore data expires automatically after a given time frame.
 *
 * If you want to be able to share data between users or use it for locking, use
 * SharedTempStore.
 *
 * @see \Drupal\Core\TempStore\SharedTempStore
 */
class PrivateTempStore implements PrivateTempStoreInterface {

  use DependencySerializationTrait;

  /**
   * The key/value storage object used for this data.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $storage;

  /**
   * The lock object used for this data.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lockBackend;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The time to live for items in seconds.
   *
   * By default, data is stored for one week (604800 seconds) before expiring.
   *
   * @var int
   */
  protected $expire;

  /**
   * Constructs a new object for accessing data from a key/value store.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $storage
   *   The key/value storage object used for this data. Each storage object
   *   represents a particular collection of data and will contain any number
   *   of key/value pairs.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(KeyValueStoreExpirableInterface $storage, LockBackendInterface $lock_backend, AccountProxyInterface $current_user, RequestStack $request_stack, $expire = 604800) {
    $this->storage = $storage;
    $this->lockBackend = $lock_backend;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->expire = $expire;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    $key = $this->createKey($key);
    if (($object = $this->storage->get($key)) && ($object->owner == $this->getOwner())) {
      return $object->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    if ($this->currentUser->isAnonymous()) {
      $session = $this->requestStack->getSession();
      if (!$session->has('core.tempstore.private.owner')) {
        $session->set('core.tempstore.private.owner', Crypt::randomBytesBase64());
      }
    }

    $key = $this->createKey($key);
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }

    $value = (object) [
      'owner' => $this->getOwner(),
      'data' => $value,
      'updated' => (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME'),
    ];
    $this->storage->setWithExpire($key, $value, $this->expire);
    $this->lockBackend->release($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($key) {
    $key = $this->createKey($key);
    // Fetch the key/value pair and its metadata.
    $object = $this->storage->get($key);
    if ($object) {
      // Don't keep the data itself in memory.
      unset($object->data);
      return new Lock($object->owner, $object->updated);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $key = $this->createKey($key);
    if (!$object = $this->storage->get($key)) {
      return TRUE;
    }
    elseif ($object->owner != $this->getOwner()) {
      return FALSE;
    }
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to delete item '$key' from '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }
    $this->storage->delete($key);
    $this->lockBackend->release($key);
    return TRUE;
  }

  /**
   * Ensures that the key is unique for a user.
   *
   * @param string $key
   *   The key.
   *
   * @return string
   *   The unique key for the user.
   */
  protected function createKey($key) {
    return $this->getOwner() . ':' . $key;
  }

  /**
   * Gets the current owner based on the current user or the session ID.
   *
   * @return string
   *   The owner.
   */
  protected function getOwner() {
    $owner = $this->currentUser->id();
    if ($this->currentUser->isAnonymous()) {
      // Check to see if an owner key exists in the session.
      $session = $this->requestStack->getSession();
      $owner = $session->get('core.tempstore.private.owner');
    }
    return $owner;
  }

}
