<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Exception\IdentifierException;
use Drupal\Core\Database\Identifier\IdentifierHandlerBase;
use Drupal\Core\Database\Identifier\IdentifierType;

/**
 * SQLite implementation of the identifier handler.
 */
class IdentifierHandler extends IdentifierHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getMaxLength(IdentifierType $type): int {
    // There is no hard limit on identifier length in SQLite, so we just use
    // common sense: identifiers longer than 128 characters are hardly
    // readable.
    // @see https://www.sqlite.org/limits.html
    // @see https://stackoverflow.com/questions/8135013/table-name-limit-in-sqlite-android
    return 128;
  }

  /**
   * {@inheritdoc}
   */
  public function parseTableIdentifier(string $identifier): array {
    $parts = parent::parseTableIdentifier($identifier);
    if ($parts['database']) {
      throw new IdentifierException(sprintf(
        'SQLite does not support the syntax [database.][schema.]table for the table identifier \'%s\'. Avoid specifying the \'database\' part',
        $identifier,
      ));
    }
    if ($this->tablePrefix !== '' && $parts['schema'] === NULL) {
      $parts['schema'] = $this->schema(rtrim($this->tablePrefix, '.'));
      $parts['needs_prefix'] = FALSE;
    }
    return $parts;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveTableForMachine(string $canonicalName, array $info): string {
    return $canonicalName;
  }

}
