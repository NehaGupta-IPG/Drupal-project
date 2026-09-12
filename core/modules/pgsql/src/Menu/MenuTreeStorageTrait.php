<?php

declare(strict_types=1);

namespace Drupal\pgsql\Menu;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\pgsql\TableVerifyTrait;

/**
 * Makes sure the menu tree table exists before use in a transaction.
 *
 * The trait carries the override that both menu tree storages share, because
 * the PostgreSQL storages extend their own core class and cannot share the
 * code through inheritance.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
trait MenuTreeStorageTrait {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  protected function safeExecuteSelect(SelectInterface $query) {
    $this->verifyTableInTransaction($this->table);
    return parent::safeExecuteSelect($query);
  }

}
