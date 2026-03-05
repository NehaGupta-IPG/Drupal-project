<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

/**
 * Interface for conditions on JSON-backed fields.
 */
interface JsonConditionInterface extends StrictSqlParamsConditionInterface {

  /**
   * Builds a conditional clause on a JSON-backed field.
   *
   * @param string $field
   *   The name of the field to check.
   * @param string $jsonpath
   *   The jsonpath for value comparison.
   * @param string|int|array|bool|null $value
   *   The value to test the field against. Unlike most other SQL queries, which
   *   match with automatic type-casting, JSON data comparisons are more
   *   sensitive to type-matching, specifically for integers and boolean values.
   *   Ensure the comparison value provided here matches the expected type found
   *   at the queried jsonpath.
   * @param string|null $operator
   *   The operator to use. Supported for all supported databases are at least:
   *   - The comparison operators =, <>, !=, <, <=, >, >=.
   *   Defaults to =.
   *
   * @return $this
   *   The called object.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If passed invalid arguments, such as an empty array as $value.
   */
  public function jsonCondition(string $field, string $jsonpath, string|int|float|array|bool|null $value = NULL, string $operator = '='): self;

}
