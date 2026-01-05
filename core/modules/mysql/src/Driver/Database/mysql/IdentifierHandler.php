<?php

declare(strict_types=1);

namespace Drupal\mysql\Driver\Database\mysql;

use Drupal\Core\Database\Exception\IdentifierException;
use Drupal\Core\Database\Identifier\IdentifierHandlerBase;
use Drupal\Core\Database\Identifier\IdentifierType;

/**
 * MySQL implementation of the identifier handler.
 */
class IdentifierHandler extends IdentifierHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getMaxLength(IdentifierType $type): int {
    // MySql allows 64 chars long identifiers, with exception of aliases
    // that can be 256 char long. However in Drupal, at the moment, we
    // treat all identifiers as max 64 chars long.
    // @see https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html
    return 64;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCanonicalName(string $identifier, string $canonicalName, IdentifierType $type): true {
    return match ($type) {
      IdentifierType::Table => TRUE,
      default => parent::validateCanonicalName($identifier, $canonicalName, $type),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function parseTableIdentifier(string $identifier): array {
    $parts = parent::parseTableIdentifier($identifier);
    if ($parts['database']) {
      throw new IdentifierException(sprintf(
        'MySql does not support the syntax [database.][schema.]table for the table identifier \'%s\'. Avoid specifying the \'database\' part',
        $identifier,
      ));
    }
    return $parts;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveTableForMachine(string $canonicalName, array $info): string {
    if ($info['schema']) {
      // We are processing a fully qualified table identifier, canonical name
      // should not be processed, just checked it does not exceed max length.
      if (strlen($canonicalName) > $this->getMaxLength(IdentifierType::Table)) {
        throw new IdentifierException(sprintf(
          'Table identifier \'%s\' exceeds maximum allowed length (%d)',
          $canonicalName,
          $this->getMaxLength(IdentifierType::Table),
        ));
      }
      return $canonicalName;
    }

    $prefix = $info['needs_prefix'] ? $this->tablePrefix : '';
    if (strlen($prefix . $canonicalName) > $this->getMaxLength(IdentifierType::Table)) {
      // We shorten too long table names.
      return $this->cropByHashing($canonicalName, $info, IdentifierType::Table, $prefix, $this->getMaxLength(IdentifierType::Table), 10);
    }
    return $prefix . $canonicalName;
  }

}
