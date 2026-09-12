<?php

declare(strict_types=1);

namespace Drupal\pgsql\Flood;

use Drupal\Core\Flood\DatabaseBackend as CoreDatabaseBackend;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database flood backend.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseBackend extends CoreDatabaseBackend {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function register($name, $window = 3600, $identifier = NULL): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::register($name, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function clear($name, $identifier = NULL): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::clear($name, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function clearByPrefix(string $name, string $prefix): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::clearByPrefix($name, $prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    return parent::isAllowed($name, $threshold, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    $this->verifyTableInTransaction(static::TABLE_NAME);
    parent::garbageCollection();
  }

}
