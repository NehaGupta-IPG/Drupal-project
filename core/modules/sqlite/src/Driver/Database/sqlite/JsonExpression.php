<?php

declare(strict_types=1);

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Query\JsonCastType;
use Drupal\Core\Database\Query\JsonExpression as BaseJsonExpression;

/**
 * SQLite implementation of JsonExpression.
 */
class JsonExpression extends BaseJsonExpression {

  /**
   * {@inheritdoc}
   */
  protected function doToSql(): string {
    return "json_extract({$this->field}, {$this->connection->quote($this->path)})";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL, ?string $type_expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    if ($expression === NULL) {
      $type_expression = "json_type({$this->field}, {$this->connection->quote($this->path)})";
    }
    return match ($type) {
      JsonCastType::Bool, JsonCastType::Int => "CAST({$sql} AS INTEGER)",
      JsonCastType::Float => "CAST({$sql} AS REAL)",
      // json_extract() returns a JSON boolean as the integer 1 or 0, while the
      // text extraction on MySQL and PostgreSQL returns 'true' or 'false'.
      // json_type() is the only way to tell the boolean from a number, so when
      // a type expression is available the CASE maps it back to the literal.
      // Without one a plain CAST is all that is possible. json_extract() also
      // returns a JSON number as SQL INTEGER or REAL, so every other value is
      // cast to TEXT like on the other backends. CAST keeps NULL, so a missing
      // path and a JSON null still read as SQL NULL.
      // @see https://www.sqlite.org/json1.html#the_json_extract_function
      JsonCastType::Text => $type_expression === NULL ? "CAST({$sql} AS TEXT)" : "CASE {$type_expression} WHEN 'true' THEN 'true' WHEN 'false' THEN 'false' ELSE CAST({$sql} AS TEXT) END",
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function doToHasKeySql(): string {
    return "json_type({$this->field}, {$this->connection->quote($this->path)}) IS NOT NULL";
  }

  /**
   * {@inheritdoc}
   */
  protected function doToContainsSql(string $encoded_value): string {
    // SQLite has no containment operator. The value to look for (the
    // candidate) is known while the query is built, so the test follows its
    // structure. json_decode() with $associative = TRUE turns both JSON arrays
    // and JSON objects into PHP arrays, so one code path handles both.
    $candidate = json_decode($encoded_value, TRUE, flags: JSON_THROW_ON_ERROR);
    $source = "{$this->field}, {$this->connection->quote($this->path)}";
    // The candidate and the value at the path have to be of the same JSON type.
    // MySQL and PostgreSQL compare an array against an array and an object
    // against an object as well.
    $type = array_is_list($candidate) ? 'array' : 'object';
    $parts = $this->containsPartsSql($candidate, $source, 0);
    $type_test = "json_type({$source}) = '{$type}'";
    return $parts === '' ? "({$type_test})" : "({$type_test} AND {$parts})";
  }

  /**
   * Builds the containment test of every part of a candidate value.
   *
   * For a JSON array, every element of $candidate has to be contained in some
   * element of the stored array. For a JSON object, every member of $candidate
   * has to be contained in the stored member of the same name. Both are tests
   * over the rows json_each() returns, so they differ only in whether the key
   * is compared.
   *
   * @param array $candidate
   *   The decoded candidate, a list for a JSON array or an associative array
   *   for a JSON object.
   * @param string $source
   *   The arguments of the json_each() call reading the value the candidate is
   *   tested against, written as "<document>, <jsonpath>".
   * @param int $depth
   *   The nesting depth of the candidate, which keeps the aliases of nested
   *   subqueries apart.
   *
   * @return string
   *   The SQL testing that the value contains every part of the candidate, or
   *   an empty string when the candidate holds no parts.
   */
  protected function containsPartsSql(array $candidate, string $source, int $depth): string {
    $alias = 'jc' . $depth;
    $is_list = array_is_list($candidate);
    $tests = [];
    foreach ($candidate as $key => $part) {
      $key_test = $is_list ? '' : "{$alias}.key = {$this->connection->quote((string) $key)} AND ";
      $tests[] = "EXISTS (SELECT 1 FROM json_each({$source}) {$alias} WHERE {$key_test}{$this->containsPartSql($part, $alias, $depth)})";
    }
    return implode(' AND ', $tests);
  }

  /**
   * Builds the containment test of one part of a candidate value.
   *
   * @param array|string|int|float|bool|null $part
   *   An element of a candidate array, or the value of a member of a candidate
   *   object, as json_decode() returns it.
   * @param string $alias
   *   The alias of the json_each() row holding the value to test.
   * @param int $depth
   *   The nesting depth of $part.
   *
   * @return string
   *   The SQL testing that the value of the row contains $part.
   */
  protected function containsPartSql(array|string|int|float|bool|null $part, string $alias, int $depth): string {
    if (is_array($part)) {
      // json_each() returns an array or an object as JSON text, so the same
      // test applies to its parts one level down.
      $type = array_is_list($part) ? 'array' : 'object';
      $nested = $this->containsPartsSql($part, "{$alias}.value, {$this->connection->quote('$')}", $depth + 1);
      $type_test = "{$alias}.type = '{$type}'";
      return $nested === '' ? $type_test : "{$type_test} AND ({$nested})";
    }
    // A value of another JSON type than the candidate is not contained, so the
    // type is tested before the value. For the literals true, false and null
    // the type column holds the value, and no further test is needed.
    return match (TRUE) {
      is_bool($part) => "{$alias}.type = '" . ($part ? 'true' : 'false') . "'",
      $part === NULL => "{$alias}.type = 'null'",
      // json_type() reports a whole number as 'integer' and a fractional one
      // as 'real', so both are accepted and the value comparison does the
      // rest. The number is written as a numeric literal. A quoted literal
      // would be compared as text and never equal a numeric value.
      is_int($part), is_float($part) => "{$alias}.type IN ('integer', 'real') AND {$alias}.value = " . json_encode($part, JSON_THROW_ON_ERROR),
      default => "{$alias}.type = 'text' AND {$alias}.value = {$this->connection->quote((string) $part)}",
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardRowSet(): array {
    // The SQLite path syntax has no wildcard. json_each() iterates the values
    // at the wildcard level, and the value of each row is read at the
    // remaining suffix path.
    // @see https://www.sqlite.org/json1.html#path_arguments
    [$prefix, $suffix] = $this->splitAtWildcard();
    // json_each walks object members and array elements alike. Filter on the
    // container type so [*] matches only arrays and .* only objects, like
    // MySQL and PostgreSQL.
    $shape = $this->wildcardAccessor() === self::WILDCARD_ELEMENT ? 'array' : 'object';
    $row_filter = "json_type({$this->field}, {$this->connection->quote($prefix)}) = '{$shape}'";
    if ($suffix === '$') {
      $extract = 'je.value';
      $element_type = 'je.type';
    }
    else {
      $extract = "json_extract(je.value, {$this->connection->quote($suffix)})";
      $element_type = "json_type(je.value, {$this->connection->quote($suffix)})";
      // json_each() returns a JSON string unquoted, and json_extract() on such
      // a value raises a "malformed JSON" error. Filter to object and array
      // values only.
      // @see https://www.sqlite.org/json1.html#jeach
      $row_filter .= " AND je.type IN ('object', 'array')";
    }
    return [
      'from' => "json_each({$this->field}, {$this->connection->quote($prefix)}) je",
      'value' => $extract,
      'type' => $element_type,
      'filter' => $row_filter,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    // SQLite has no boolean JSON type. json_type() reports the literals true
    // and false as their own types. A whole number satisfies a float
    // comparison, so 'integer' is accepted for both numeric casts.
    // @see https://www.sqlite.org/json1.html#jtype
    $json_types = match ($type) {
      JsonCastType::Int, JsonCastType::Float => "'integer', 'real'",
      JsonCastType::Bool => "'true', 'false'",
      JsonCastType::Text => "'text'",
    };
    $type_expression ??= "json_type({$this->field}, {$this->connection->quote($this->path)})";
    return "{$type_expression} IN ({$json_types})";
  }

}
