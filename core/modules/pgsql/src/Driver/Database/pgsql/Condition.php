<?php

namespace Drupal\pgsql\Driver\Database\pgsql;

use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\Database\Query\Condition as BaseCondition;
use Drupal\Core\Database\Query\PlaceholderInterface;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * PostgreSQL implementation of query conditions.
 */
class Condition extends BaseCondition {

  /**
   * {@inheritdoc}
   */
  public function compile(DatabaseConnection $connection, PlaceholderInterface $queryPlaceholder) {
    $original_conditions = $this->conditions;

    if ($queryPlaceholder instanceof SelectInterface && $connection->schema() instanceof Schema) {
      $this->conditions = $this->filterInvalidIntegerInValues($connection->schema(), $queryPlaceholder, $this->conditions);
    }

    try {
      parent::compile($connection, $queryPlaceholder);
    }
    finally {
      // Keep Condition::conditions() stable for callers.
      // The compiled string and arguments retain the PostgreSQL-specific
      // filtering performed above.
      $this->conditions = $original_conditions;
    }
  }

  /**
   * Removes values that PostgreSQL cannot represent in integer IN conditions.
   *
   * PostgreSQL resolves the comparison against the native column type and will
   * throw SQLSTATE 22P02 or 22003 before an impossible value can simply fail to
   * match. Invalid values can therefore be discarded from an IN condition.
   */
  protected function filterInvalidIntegerInValues(Schema $schema, SelectInterface $query, array $conditions): array {
    foreach ($conditions as $key => $condition) {
      if ($key === '#conjunction'
        || ($condition['operator'] ?? NULL) !== 'IN'
        || !is_string($condition['field'])
        || !is_array($condition['value'])) {
        continue;
      }

      $field = $this->resolveTableField($query, $condition['field']);
      if ($field === NULL) {
        continue;
      }
      [$table, $column] = $field;

      $values = [];
      foreach ($condition['value'] as $value) {
        // Avoid PostgreSQL catalog introspection on the normal path. Values in
        // the signed 32-bit range have valid syntax for all commonly used
        // Drupal entity ID columns. Inspect the actual native type only for
        // malformed or potentially out-of-range values.
        if (!$this->needsIntegerValidation($value)) {
          $values[] = $value;
          continue;
        }

        $storable = $schema->isStorableIntegerValue($table, $column, $value);
        if ($storable !== FALSE) {
          $values[] = $value;
        }
      }

      if ($values === $condition['value']) {
        continue;
      }
      if ($values) {
        $conditions[$key]['value'] = array_values($values);
      }
      else {
        // An empty IN list is invalid SQL and Condition::condition() disallows
        // it, so compile the now-impossible condition as an explicit false
        // expression instead.
        $conditions[$key] = [
          'field' => '1 = 0',
          'value' => [],
          'operator' => NULL,
        ];
      }
    }

    return $conditions;
  }

  /**
   * Determines whether a condition value needs native integer validation.
   */
  protected function needsIntegerValidation(mixed $value): bool {
    if (!is_int($value) && !is_string($value)) {
      return FALSE;
    }

    $value = trim((string) $value);
    if (!preg_match('/^[+-]?\d+$/D', $value)) {
      return TRUE;
    }

    $negative = str_starts_with($value, '-');
    if ($negative || str_starts_with($value, '+')) {
      $value = substr($value, 1);
    }
    $value = ltrim($value, '0');
    if ($value === '') {
      return FALSE;
    }

    // Values outside a signed 32-bit integer are uncommon in normal entity ID
    // queries and need the actual PostgreSQL field type to determine whether
    // they fit an integer or bigint column.
    $limit = $negative ? '2147483648' : '2147483647';
    return strlen($value) > strlen($limit)
      || (strlen($value) === strlen($limit) && strcmp($value, $limit) > 0);
  }

  /**
   * Resolves a condition field to a physical table and column where possible.
   *
   * @return array{string, string}|null
   *   The non-prefixed table and column, or NULL when the field cannot be
   *   resolved safely (for example, when it belongs to a subquery).
   */
  protected function resolveTableField(SelectInterface $query, string $field): ?array {
    $tables = $query->getTables();

    if (str_contains($field, '.')) {
      [$alias, $column] = explode('.', $field, 2);
      if (!isset($tables[$alias]) || !is_string($tables[$alias]['table'])) {
        return NULL;
      }
      $table = $tables[$alias]['table'];
    }
    else {
      $physical_tables = array_filter($tables, static fn (array $table): bool => is_string($table['table']));
      if (count($physical_tables) !== 1) {
        return NULL;
      }
      $table_info = reset($physical_tables);
      $table = $table_info['table'];
      $column = $field;
    }

    // Schema::getNativeFieldType() expects a non-qualified Drupal table name.
    // Let conditions on schema-qualified tables or expressions compile without
    // PostgreSQL-specific filtering.
    if (str_contains($table, '.') || !preg_match('/^[A-Za-z0-9_]+$/D', $table) || !preg_match('/^[A-Za-z0-9_]+$/D', $column)) {
      return NULL;
    }

    return [$table, $column];
  }

}
