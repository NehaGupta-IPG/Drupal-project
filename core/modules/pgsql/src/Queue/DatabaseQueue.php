<?php

declare(strict_types=1);

namespace Drupal\pgsql\Queue;

use Drupal\Core\Queue\DatabaseQueue as CoreDatabaseQueue;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database queue.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseQueue extends CoreDatabaseQueue {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::createItem($data);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::numberOfItems();
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::claimItem($lease_time);
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::releaseItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function delayItem($item, int $delay) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::delayItem($item, $delay);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::deleteItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::deleteQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::garbageCollection();
  }

}
