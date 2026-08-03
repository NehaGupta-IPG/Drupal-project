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
  protected function doToCastSql(JsonCastType $type, ?string $expression = NULL): string {
    $sql = $expression ?? $this->toSql();
    return match ($type) {
      JsonCastType::Bool, JsonCastType::Int => "CAST({$sql} AS INTEGER)",
      JsonCastType::Float => "CAST({$sql} AS REAL)",
      JsonCastType::Text => $sql,
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
    // SQLite has no containment operator, so the test is built from the
    // structure of the candidate, which is known while the query is built.
    // Decoding the encoded candidate normalizes it to arrays and scalars,
    // whichever of those the caller combined it from.
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
   * An element of a candidate array is contained when any element of the value
   * at the path contains it, a member of a candidate object when the member of
   * the same name does. Both are a test over the rows json_each() returns, so
   * the two cases differ in the test of the key only.
   *
   * @param array $candidate
   *   The candidate array or object.
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
   * @param mixed $part
   *   An element of a candidate array, or the value of a member of a candidate
   *   object.
   * @param string $alias
   *   The alias of the json_each() row holding the value to test.
   * @param int $depth
   *   The nesting depth of $part.
   *
   * @return string
   *   The SQL testing that the value of the row contains $part.
   */
  protected function containsPartSql(mixed $part, string $alias, int $depth): string {
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
      // A whole number is reported as an integer and a fractional one as a
      // real, so both types satisfy a numeric candidate and the comparison
      // decides. The number is written as a numeric literal, because a quoted
      // one would compare as text and equal no number.
      is_int($part), is_float($part) => "{$alias}.type IN ('integer', 'real') AND {$alias}.value = " . json_encode($part, JSON_THROW_ON_ERROR),
      default => "{$alias}.type = 'text' AND {$alias}.value = {$this->connection->quote((string) $part)}",
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function wildcardRowSet(): array {
    // SQLite doesn't support wildcards in json_extract. Use json_each to
    // iterate the wildcard level and read each element's value at the
    // remaining suffix path. json_each walks the members of an object and the
    // elements of an array alike, so the prefix alone covers both the .* and
    // the [*] accessor.
    [$prefix, $suffix] = $this->splitAtWildcard();
    if ($suffix === '$') {
      $extract = 'je.value';
      $element_type = 'je.type';
      $row_filter = '';
    }
    else {
      $extract = "json_extract(je.value, {$this->connection->quote($suffix)})";
      $element_type = "json_type(je.value, {$this->connection->quote($suffix)})";
      // json_extract on a scalar je.value (e.g. a string) causes a
      // "malformed JSON" error. Filter to object/array values only.
      $row_filter = "je.type IN ('object', 'array') AND ";
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
    // SQLite has no boolean JSON type, it reports the literals true and false
    // as their own type. A whole number satisfies a float comparison, so
    // 'integer' is accepted for both numeric casts.
    $json_types = match ($type) {
      JsonCastType::Int, JsonCastType::Float => "'integer', 'real'",
      JsonCastType::Bool => "'true', 'false'",
      JsonCastType::Text => '',
    };
    if ($json_types === '') {
      return '';
    }
    $type_expression ??= "json_type({$this->field}, {$this->connection->quote($this->path)})";
    return "{$type_expression} IN ({$json_types}) AND ";
  }

}
