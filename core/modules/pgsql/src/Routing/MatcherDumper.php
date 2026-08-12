<?php

declare(strict_types=1);

namespace Drupal\pgsql\Routing;

use Drupal\Core\Routing\MatcherDumper as CoreMatcherDumper;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database matcher dumper.
 *
 * The table is verified before every dump, not only when a caller already
 * started a transaction, because dump() starts its own transaction.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class MatcherDumper extends CoreMatcherDumper {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function dump(array $options = []): string {
    $this->verifyTable($this->tableName);
    return parent::dump($options);
  }

}
