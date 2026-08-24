<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database GIN (Generalized Inverted Index) index.
 *
 * GIN indexes are currently implemented by PostgreSQL only. Drivers for
 * databases that do not support them skip the index. Use AlternativeIndexes
 * to specify a fallback index to be created in that case.
 */
final class GinIndex extends KeyBase implements IndexInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The index name.
   * @param list<\Drupal\Core\Database\SchemaDefinition\KeyColumn|string> $columns
   *   A mix of key column specifiers, being KeyColumn objects or strings
   *   naming columns. Limited length columns are not allowed.
   */
  public function __construct(
    public readonly string $name,
    array $columns,
  ) {
    parent::__construct($columns, FALSE);
  }

  /**
   * {@inheritdoc}
   *
   * The legacy array-based specifications can only describe regular indexes.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  public function toArray(): array {
    throw new SchemaDefinitionException("The '{$this->name}' GinIndex cannot be converted to legacy array-based specifications");
  }

}
