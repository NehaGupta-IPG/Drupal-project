<?php

declare(strict_types=1);

namespace Drupal\Core\Database;

use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Provides condition handling for JSON fields with generated columns.
 */
trait JsonpathGeneratedFieldConditionTrait {

  /**
   * Get the field fragment for a jsonpath condition.
   *
   * This method adds an additional parameter to enable optimization on an
   * additional condition, and can be re-used between MySQL and SQLite.
   *
   * @param string $field_name
   *   Field name.
   * @param array $condition
   *   Condition definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $query_placeholder
   *   Query placeholder.
   * @param bool $enableOptimization
   *   Whether to enable optimization. Defaults to TRUE.
   *
   * @return string
   *   Field fragment.
   */
  protected function getJsonFieldFragment(string $field_name, array $condition, Connection $connection, PlaceholderInterface $query_placeholder, bool $enableOptimization = TRUE): string {
    if ($enableOptimization) {
      // MariaDB nor SQLite will automatically select an applicable index based
      // on a generated column matching the JSON path. Query the generated
      // column directly, if it exists from a json_hotpaths specification.
      $schema = $connection->schema();
      if (method_exists($schema, 'getJsonpathGeneratedFieldName') && $query_placeholder instanceof SelectInterface) {
        // Determine if the generated field exists.
        $split_field = explode('.', $field_name, 2);
        $tables = $query_placeholder->getTables();
        $table = count($split_field) === 2
          ? array_reduce($tables, function (string $table, array $table_definition) use ($split_field): string {
            return $table_definition['alias'] === $split_field[0] ? $table_definition['table'] : $table;
          }, '')
          : array_reduce(
            $tables,
            fn (string $table, array $table_definition): string => $table_definition['join type'] === NULL ? $table_definition['table'] : $table,
            ''
          );
        $candidate = $schema->getJsonpathGeneratedFieldName($field_name, $condition['jsonpath']);
        // @todo This could maybe benefit from caching, but that's still another request.
        if ($connection->schema()->fieldExists($table, $candidate)) {
          return $candidate;
        }
      }
    }
    return $this->getJsonFieldFragmentFunction(
      $condition['field'],
      $condition['jsonpath'],
      $condition['value'],
      $connection
    );
  }

  /**
   * Get the SQL function statement for retrieving a JSON value.
   *
   * It may be necessary to override this method if the driver requires special
   * handling of return types or other unique value-matching behavior. This
   * base case is standard SQL syntax.
   */
  protected function getJsonFieldFragmentFunction(string $field, string $jsonpath, mixed $value, Connection $connection): string {
    return "JSON_EXTRACT({$field}, '{$jsonpath}')";
  }

}
