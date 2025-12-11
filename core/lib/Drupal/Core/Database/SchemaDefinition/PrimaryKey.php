<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a table's primary key.
 */
final class PrimaryKey extends KeyBase {

  /**
   * Constructor.
   *
   * @param list<KeyColumn|string|array{0:string, 1:int}> $columns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   */
  public function __construct(array $columns) {
    parent::__construct($columns);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->keyColumnsToArray($this->columns);
  }

}
