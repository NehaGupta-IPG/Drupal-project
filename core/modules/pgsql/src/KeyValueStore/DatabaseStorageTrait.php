<?php

declare(strict_types=1);

namespace Drupal\pgsql\KeyValueStore;

use Drupal\pgsql\TableVerifyTrait;

/**
 * Makes sure the key/value table exists before use in a transaction.
 *
 * The trait carries the overrides of the methods that both key/value storages
 * share, because the PostgreSQL storages extend their own core class and
 * cannot share the code through inheritance.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
trait DatabaseStorageTrait {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    $this->verifyTableInTransaction($this->table);
    return parent::has($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    $this->verifyTableInTransaction($this->table);
    return parent::getMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    $this->verifyTableInTransaction($this->table);
    return parent::getAll();
  }

  /**
   * {@inheritdoc}
   */
  public function getAllKeys(): iterable {
    $this->verifyTableInTransaction($this->table);
    return parent::getAllKeys();
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value): void {
    $this->verifyTableInTransaction($this->table);
    parent::set($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data): void {
    $this->verifyTableInTransaction($this->table);
    parent::setMultiple($data);
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    $this->verifyTableInTransaction($this->table);
    return parent::setIfNotExists($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key): void {
    $this->verifyTableInTransaction($this->table);
    parent::rename($key, $new_key);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys): void {
    $this->verifyTableInTransaction($this->table);
    parent::deleteMultiple($keys);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->verifyTableInTransaction($this->table);
    parent::deleteAll();
  }

}
