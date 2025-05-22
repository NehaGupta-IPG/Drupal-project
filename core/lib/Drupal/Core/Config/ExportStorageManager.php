<?php

namespace Drupal\Core\Config;

use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The export storage manager dispatches an event for the export storage.
 *
 * This class is not meant to be extended and is final to make sure the
 * constructor and the getStorage method are both changed when this pattern is
 * used in other circumstances.
 */
final class ExportStorageManager implements StorageManagerInterface {

  use StorageCopyTrait;

  /**
   * The name used to identify the lock.
   */
  const LOCK_NAME = 'config_storage_export_manager';

  /**
   * ExportStorageManager constructor.
   *
   * @param \Drupal\Core\Config\StorageInterface $active
   *   The active config storage to prime the export storage.
   * @param \Drupal\Core\Config\StorageInterface $storage
   *   The export storage.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The used lock backend instance.
   */
  public function __construct(
    protected StorageInterface $active,
    protected StorageInterface $storage,
    protected EventDispatcherInterface $eventDispatcher,
    protected LockBackendInterface $lock,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStorage() {
    // Acquire a lock for the request to assert that the storage does not change
    // when a concurrent request transforms the storage.
    if (!$this->lock->acquire(self::LOCK_NAME)) {
      $this->lock->wait(self::LOCK_NAME);
      if (!$this->lock->acquire(self::LOCK_NAME)) {
        throw new StorageTransformerException("Cannot acquire config export transformer lock.");
      }
    }

    // Wrapping the queries in a transaction for performance gain.
    $transaction = $this->connection->startTransaction();
    self::replaceStorageContents($this->active, $this->storage);
    unset($transaction);

    $this->eventDispatcher->dispatch(new StorageTransformEvent($this->storage), ConfigEvents::STORAGE_TRANSFORM_EXPORT);

    return new ReadOnlyStorage($this->storage);
  }

}
