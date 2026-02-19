<?php

namespace Drupal\Core\TempStore;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Creates a PrivateTempStore object for a given collection.
 */
class PrivateTempStoreFactory {

  /**
   * The storage factory creating the backend to store the data.
   *
   * @var KeyValueExpirableFactoryInterface
   */
  protected $storageFactory;

  /**
   * The lock object used for this data.
   *
   * @var LockBackendInterface
   */
  protected $lockBackend;

  /**
   * The current user.
   *
   * @var AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * The time to live for items in seconds.
   *
   * @var int
   */
  protected $expire;

  /**
   * Constructs a Drupal\Core\TempStore\PrivateTempStoreFactory object.
   *
   * @param KeyValueExpirableFactoryInterface $storage_factory
   *   The key/value store factory.
   * @param LockBackendInterface $lock_backend
   *   The lock object used for this data.
   * @param AccountProxyInterface $current_user
   *   The current account.
   * @param RequestStack $request_stack
   *   The request stack.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function __construct(KeyValueExpirableFactoryInterface $storage_factory, LockBackendInterface $lock_backend, AccountProxyInterface $current_user, RequestStack $request_stack, $expire = 604800) {
    $this->storageFactory = $storage_factory;
    $this->lockBackend = $lock_backend;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->expire = $expire;
  }

  /**
   * Creates a PrivateTempStore.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   *
   * @return PrivateTempStore
   *   An instance of the key/value store.
   */
  public function get($collection) {
    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("tempstore.private.$collection");
    return new PrivateTempStore($storage, $this->lockBackend, $this->currentUser, $this->requestStack, $this->expire);
  }

}
