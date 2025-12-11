<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database schema as a collection of Table objects.
 */
final class Schema implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param string $name
   *   The schema name.
   * @param list<Table> $tables
   *   A list of Table objects.
   */
  public function __construct(
    public readonly string $name,
    public readonly array $tables,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $spec = [];
    foreach ($this->tables as $table) {
      $spec[$table->name] = $table->toArray();
    }
    return $spec;
  }

}
