<?php

declare(strict_types=1);

namespace Drupal\pgsql\Cache;

use Drupal\Core\Cache\DatabaseBackend as CoreDatabaseBackend;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database cache backend.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseBackend extends CoreDatabaseBackend {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $this->verifyTableInTransaction($this->bin);
    return parent::getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items): void {
    $this->verifyTableInTransaction($this->bin);
    parent::setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids): void {
    $this->verifyTableInTransaction($this->bin);
    parent::deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->verifyTableInTransaction($this->bin);
    parent::deleteAll();
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids): void {
    $this->verifyTableInTransaction($this->bin);
    parent::invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    $this->verifyTableInTransaction($this->bin);
    parent::garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin(): void {
    unset($this->verifiedTables[$this->bin]);
    if ($this->connection->inTransaction() && !$this->connection->schema()->tableExists($this->bin)) {
      // Nothing to drop, and a failing drop would abort the transaction.
      return;
    }
    parent::removeBin();
  }

}
