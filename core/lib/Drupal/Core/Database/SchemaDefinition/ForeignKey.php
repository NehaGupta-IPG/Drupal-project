<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database foreign key.
 */
final class ForeignKey extends KeyBase {

  /**
   * The foreign key columns.
   *
   * @var KeyColumn[]
   *   The list of KeyColumn objects of the foreign table.
   */
  public readonly array $foreignColumns;

  /**
   * Constructor.
   *
   * @param string $name
   *   The foreign key name.
   * @param string $foreignTable
   *   The foreign (referenced) table name.
   * @param list<KeyColumn|string|array{0:string, 1:int}> $columns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   * @param list<KeyColumn|string|array{0:string, 1:int}> $foreignColumns
   *   A mix of key column specifiers of the foreign (referenced) table, being
   *   KeyColumn objects, strings naming columns, or arrays of two elements,
   *   column name and length, specifying a prefix of the named column.
   */
  public function __construct(
    public readonly string $name,
    public readonly string $foreignTable,
    array $columns,
    array $foreignColumns,
  ) {
    parent::__construct($columns);
    $this->foreignColumns = $this->buildColumns($foreignColumns);
    assert(count($this->columns) === count($this->foreignColumns), "Mismatching count of columns for the {$this->name} foreign key.");
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $cols = $this->keyColumnsToArray($this->columns);
    $foreignCols = $this->keyColumnsToArray($this->foreignColumns);
    $match = [];
    for ($i = 0; $i < count($cols); $i++) {
      $match[$cols[$i]] = $foreignCols[$i];
    }
    return [
      'table' => $this->foreignTable,
      'columns' => $match,
    ];
  }

}
