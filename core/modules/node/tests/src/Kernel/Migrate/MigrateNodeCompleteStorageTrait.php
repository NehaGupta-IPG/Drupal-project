<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel\Migrate;

use Drupal\Core\Database\Statement\FetchAs;

/**
 * Reads migrated node destination data in a storage-agnostic way.
 *
 * The complete node migration tests verify the migration result by dumping the
 * "node_field_data" / "node_field_revision" tables. Under a JSON-storage driver
 * (for example pgsql) a content entity's field data is embedded in "jsonb"
 * columns on the entity base table, so those physical tables do not exist: the
 * default-revision documents (the "node_field_data" analogue) live under
 * "__data_current_revision" and every revision's documents (the
 * "node_field_revision" analogue) under "__data_all_revisions" on the base
 * "node" table. These helpers return the same row sets either from the physical
 * tables (regular SQL storage) or by unwinding those JSON documents, so a single
 * set of expectations covers both storage models.
 */
trait MigrateNodeCompleteStorageTrait {

  /**
   * Loads a migrated node destination table, storage-agnostically.
   *
   * @param string $table
   *   The logical destination table: "node_field_data" or
   *   "node_field_revision".
   * @param array $expected
   *   The expected rows. Used only under JSON storage, to derive the column set
   *   to project from each document.
   * @param string[] $order_by
   *   The columns to order the returned rows by, as the physical query would.
   *
   * @return array
   *   The destination rows, each an associative array of string|null values
   *   (matching what a PDO fetch of the physical table returns).
   */
  protected function loadDestinationNodeTable(string $table, array $expected, array $order_by): array {
    $database = \Drupal::database();
    if ($database->driver() !== 'pgsql') {
      $select = $database->select($table, 't')->fields('t');
      foreach ($order_by as $column) {
        $select->orderBy($column);
      }
      return $select->execute()->fetchAll(FetchAs::Associative);
    }

    // Under pgsql the physical table does not exist; unwind the corresponding
    // embedded JSON document set from the base "node" table instead.
    [$json_column, $wrapper_key] = match ($table) {
      'node_field_data' => ['__data_current_revision', 'node_current_revision'],
      'node_field_revision' => ['__data_all_revisions', 'node_all_revisions'],
    };
    $columns = array_keys($expected[0]);
    $rows = [];
    foreach ($database->query("SELECT $json_column AS document FROM {node}")->fetchCol() as $json) {
      if ($json === NULL) {
        continue;
      }
      foreach (json_decode($json, TRUE)[$wrapper_key] ?? [] as $document) {
        $row = [];
        foreach ($columns as $column) {
          // The write layer omits null columns from the stored document, so an
          // absent key is NULL. PDO returns every column as a string, so cast
          // to match (a JSON boolean maps to Drupal's '1'/'0').
          $value = $document[$column] ?? NULL;
          $row[$column] = $value === NULL ? NULL : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        }
        $rows[] = $row;
      }
    }
    usort($rows, function (array $a, array $b) use ($order_by): int {
      foreach ($order_by as $column) {
        [$x, $y] = [$a[$column], $b[$column]];
        $comparison = is_numeric($x) && is_numeric($y) ? ((int) $x <=> (int) $y) : strcmp((string) $x, (string) $y);
        if ($comparison !== 0) {
          return $comparison;
        }
      }
      return 0;
    });
    return $rows;
  }

  /**
   * Counts migrated nodes of a given type, storage-agnostically.
   *
   * @param string $type
   *   The node type (bundle).
   *
   * @return string
   *   The node count as a string, matching a COUNT() query's return.
   */
  protected function countMigratedNodesOfType(string $type): string {
    $database = \Drupal::database();
    if ($database->driver() !== 'pgsql') {
      return (string) $database->select('node_field_data')
        ->condition('type', $type)
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    // node_field_data is embedded in "jsonb" under pgsql; count via the entity
    // query, which resolves against the JSON storage.
    return (string) \Drupal::entityQuery('node')
      ->condition('type', $type)
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
