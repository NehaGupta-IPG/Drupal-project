<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Base class for table keys (primary, unique, index).
 */
abstract class KeyBase implements SchemaDefinitionInterface {

  /**
   * The key columns.
   *
   * @var KeyColumn[]
   *   The list of KeyColumn objects.
   */
  public readonly array $columns;

  /**
   * Constructor.
   *
   * @param list<KeyColumn|string|array{0:string, 1:int}> $columns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   */
  public function __construct(array $columns) {
    $this->columns = $this->buildColumns($columns);
  }

  /**
   * Builds an array of KeyColumn objects from a mixed list of columns.
   *
   * @param list<KeyColumn|string|array{0:string, 1:int}> $rawColumns
   *   A mix of key column specifiers, being KeyColumn objects, strings naming
   *   columns, or arrays of two elements, column name and length, specifying a
   *   prefix of the named column.
   *
   * @return KeyColumn[]
   *   The normalized list of KeyColumn objects.
   */
  protected function buildColumns(array $rawColumns): array {
    $columns = [];
    foreach ($rawColumns as $rawColumn) {
      if ($rawColumn instanceof KeyColumn) {
        $columns[] = $rawColumn;
      }
      elseif (is_array($rawColumn)) {
        $columns[] = new KeyColumn($rawColumn[0], $rawColumn[1]);
      }
      else {
        $columns[] = new KeyColumn($rawColumn);
      }
    }
    return $columns;
  }

  /**
   * Converts an array of KeyColumn objects to legacy array.
   *
   * @param list<KeyColumn> $keyColumns
   *   An array of KeyColumn objects.
   *
   * @return list<string|array{0:string, 1:int}>
   *   The legacy array-based specifications.
   *
   * @internal
   */
  protected function keyColumnsToArray(array $keyColumns): array {
    $spec = [];
    foreach ($keyColumns as $keyColumn) {
      $spec[] = $keyColumn->toArray()[0];
    }
    return $spec;
  }

}
