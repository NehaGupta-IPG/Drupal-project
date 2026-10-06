<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Database\Exception\SchemaDefinitionException;

/**
 * Describes a database schema as a collection of database objects.
 */
final class Schema implements SchemaDefinitionInterface {

  /**
   * Constructor.
   *
   * @param SchemaDefinitionType $type
   *   The type of Drupal feature providing the schema.
   * @param string $name
   *   The schema name.
   * @param list<Table> $tables
   *   A list of Table objects.
   * @param list<View> $views
   *   (optional) A list of View objects in creation order. Declare each view
   *   before any views that depend on it.
   */
  public function __construct(
    public readonly SchemaDefinitionType $type,
    public readonly string $name,
    public readonly array $tables,
    public readonly array $views = [],
  ) {
    $this->validate();
  }

  /**
   * Validates the properties of the value object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   */
  private function validate(): void {
    if ($this->tables && !Inspector::assertAllObjects($this->tables, Table::class)) {
      throw new SchemaDefinitionException("All members of the 'tables' argument of a Schema must be Table objects");
    }
    $tableNames = $this->tableNames();
    if (count($tableNames) !== count(array_unique($tableNames))) {
      throw new SchemaDefinitionException("Duplicate tables for '{$this->name}' schema: " . implode(', ', $tableNames));
    }
    if ($this->views && !Inspector::assertAllObjects($this->views, View::class)) {
      throw new SchemaDefinitionException("All members of the 'views' argument of a Schema must be View objects");
    }
    $viewNames = $this->viewNames();
    if (count($viewNames) !== count(array_unique($viewNames))) {
      throw new SchemaDefinitionException("Duplicate views for '{$this->name}' schema: " . implode(', ', $viewNames));
    }
    $collisions = array_intersect($viewNames, $tableNames);
    if ($collisions) {
      throw new SchemaDefinitionException("Views named like a table in '{$this->name}' schema: " . implode(', ', $collisions));
    }
  }

  /**
   * Returns the names of the tables.
   *
   * @return list<string>
   *   The names of the tables.
   */
  public function tableNames(): array {
    return array_map(
      fn(Table $table): string => $table->name,
      $this->tables,
    );
  }

  /**
   * Gets a specific table definition.
   *
   * @param string $name
   *   The name of the Table object to return.
   *
   * @return Table
   *   The Table object.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the requested table is missing.
   */
  public function getTableDefinition(string $name): Table {
    $ret = array_find(
      $this->tables,
      function (Table $table) use ($name): bool {
        return $table->name === $name;
      },
    );
    if (!$ret) {
      throw new SchemaDefinitionException("Schema {$this->name} does not define a table named {$name}");
    }
    return $ret;
  }

  /**
   * Returns the names of the SQL views.
   *
   * @return list<string>
   *   The names of the views.
   */
  public function viewNames(): array {
    return array_map(
      fn(View $view): string => $view->name,
      $this->views,
    );
  }

  /**
   * Gets a SQL view definition.
   *
   * @param string $name
   *   The view name.
   *
   * @return View
   *   The view definition.
   *
   * @throws \Drupal\Core\Database\Exception\SchemaDefinitionException
   *   When the requested view is missing.
   */
  public function getViewDefinition(string $name): View {
    $ret = array_find(
      $this->views,
      function (View $view) use ($name): bool {
        return $view->name === $name;
      },
    );
    if (!$ret) {
      throw new SchemaDefinitionException("Schema {$this->name} does not define a view named {$name}");
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    // The legacy array format cannot describe SQL views, so omit them.
    $spec = [];
    foreach ($this->tables as $table) {
      $spec[$table->name] = $table->toArray();
    }
    return $spec;
  }

}
