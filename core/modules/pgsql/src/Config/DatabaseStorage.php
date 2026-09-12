<?php

declare(strict_types=1);

namespace Drupal\pgsql\Config;

use Drupal\Core\Config\DatabaseStorage as CoreDatabaseStorage;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database config storage.
 *
 * The parent createCollection() creates the collection with "new static()",
 * so collections created from this storage keep this implementation.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class DatabaseStorage extends CoreDatabaseStorage {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    $this->verifyTableInTransaction($this->table);
    return parent::exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    $this->verifyTableInTransaction($this->table);
    return parent::read($name);
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $this->verifyTableInTransaction($this->table);
    return parent::readMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    $this->verifyTableInTransaction($this->table);
    return parent::write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    $this->verifyTableInTransaction($this->table);
    return parent::listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    $this->verifyTableInTransaction($this->table);
    return parent::deleteAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    $this->verifyTableInTransaction($this->table);
    return parent::getAllCollectionNames();
  }

}
