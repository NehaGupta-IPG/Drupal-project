<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Describes a database schema as a collection of Table objects.
 */
final class Schema implements SchemaDefinitionInterface, \Iterator {

  /**
   * The position in the iteration when object is accessed via iterator.
   */
  private int $position = 0;

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
    $this->position = 0;
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

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->position = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function current(): Table {
    return $this->tables[$this->position];
  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    ++$this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return isset($this->tables[$this->position]);
  }

}
