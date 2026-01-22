<?php

namespace Drupal\Core\TempStore;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores and retrieves temporary data for a given owner.
 *
 * The data for the SharedTempStore is stored in one key/value collection.
 * SharedTempStore data expires automatically after a given time frame.
 *
 * If you want to be able to ensure that the data belongs to the current user,
 * use PrivateTempStore.
 *
 * @see \Drupal\Core\TempStore\PrivateTempStore
 */
class SharedTempStore implements SharedTempStoreInterface {

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The owner key to store along with the data (e.g. a user or session ID).
   *
   * @var mixed
   */
  protected $owner;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

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
   * @param mixed $owner
   *   The owner key to store along with the data (e.g. a user or session ID).
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(KeyValueStoreExpirableInterface $storage, LockBackendInterface $lock_backend, $owner, RequestStack $request_stack, AccountProxyInterface $current_user, $expire = 604800) {
    $this->storage = $storage;
    $this->lockBackend = $lock_backend;
    $this->owner = $owner;
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->expire = $expire;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if ($object = $this->storage->get($key)) {
      return $object->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIfOwner($key) {
    if (($object = $this->storage->get($key)) && ($object->owner == $this->owner)) {
      return $object->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    $value = (object) [
      'owner' => $this->owner,
      'data' => $value,
      'updated' => (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME'),
    ];
    $this->ensureAnonymousSession();
    $set = $this->storage->setWithExpireIfNotExists($key, $value, $this->expire);
    return $set;
  }

  /**
   * {@inheritdoc}
   */
  public function setIfOwner($key, $value) {
    if ($this->setIfNotExists($key, $value)) {
      return TRUE;
    }

    if (($object = $this->storage->get($key)) && ($object->owner == $this->owner)) {
      $this->set($key, $value);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }

    $value = (object) [
      'owner' => $this->owner,
      'data' => $value,
      'updated' => (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME'),
    ];
    $this->ensureAnonymousSession();
    $this->storage->setWithExpire($key, $value, $this->expire);
    $this->lockBackend->release($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata($key) {
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
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to delete item '$key' from {$this->storage->getCollectionName()} temporary storage.");
      }
    }
    $this->storage->delete($key);
    $this->lockBackend->release($key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteIfOwner($key) {
    if (!$object = $this->storage->get($key)) {
      return TRUE;
    }
    elseif ($object->owner == $this->owner) {
      $this->delete($key);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Stores the owner in the session if the user is anonymous.
   *
   * This method should be called when a value is set.
   */
  protected function ensureAnonymousSession() {
    if ($this->currentUser->isAnonymous()) {
      $this->requestStack->getSession()->set('core.tempstore.shared.owner', $this->owner);
    }
  }

}
