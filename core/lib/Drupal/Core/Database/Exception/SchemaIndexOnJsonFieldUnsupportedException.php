<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Exception;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\SchemaException;

/**
 * Exception for unsupported index creation directly on a JSON column.
 */
final class SchemaIndexOnJsonFieldUnsupportedException extends SchemaException implements DatabaseException {

  /**
   * Creates an exception for a direct index on a JSON column.
   *
   * @param string $table
   *   Table name.
   * @param string $index
   *   Index name.
   * @param string $column
   *   Column name.
   */
  public static function forColumn(string $table, string $index, string $column): self {
    return new self(sprintf(
      'Database does not support creating indexes directly on JSON data column: Index %s for table %s, JSON data column %s. Use JsonIndex::on() in the indexes array instead.',
      $index,
      $table,
      $column,
    ));
  }

}
