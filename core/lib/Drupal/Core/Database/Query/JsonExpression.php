<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Builds driver-specific SQL for reading and testing a value in a JSON column.
 *
 * Each database driver provides its own subclass with driver-specific SQL.
 * Used internally by JsonCondition, Select::jsonOrderBy() and
 * Select::addJsonExpression(). To add a JSON extraction to a SELECT field list,
 * use SelectInterface::addJsonExpression() instead of this class:
 * @code
 * $alias = $query->addJsonExpression('field_data', '$.status', 'status');
 * // With a type cast:
 * $alias = $query->addJsonExpression('field_data', '$.score', 'score', JsonCastType::Int);
 * @endcode
 */
abstract class JsonExpression {

  /**
   * The column name.
   */
  protected readonly string $field;

  /**
   * The normalized JSONPath expression.
   *
   * All key names are in a format supported by all database drivers:
   * - A key written in dot notation with word characters only stays unquoted:
   *   $.key
   * - Every other key, including any key written in bracket notation, is
   *   rendered in double-quoted dot notation with " and \ escaped: $."key-x"
   * - Array indices are unchanged: $[0]
   * - Both wildcard accessors are unchanged: $.* and $[*]
   */
  protected readonly string $path;

  /**
   * The parsed path segments (key names and array indices as strings).
   *
   * Key names are decoded. A wildcard segment is self::WILDCARD_MEMBER for
   * $.* or self::WILDCARD_ELEMENT for $[*], so the two accessors stay
   * distinguishable.
   *
   * @var string[]
   */
  protected readonly array $pathSegments;

  /**
   * The parsed JSONPath segments.
   *
   * Key segments include the decoded key and a quoting flag. Index segments
   * include the index. Wildcard segments hold only their segment type.
   *
   * @var list<array{0: string, 1?: string, 2?: bool}>
   */
  private readonly array $segments;

  /**
   * The path segment of the object member wildcard, written as $.* in a path.
   */
  protected const WILDCARD_MEMBER = '*';

  /**
   * The path segment of the array element wildcard, written as $[*].
   */
  protected const WILDCARD_ELEMENT = '[*]';

  /**
   * Identifies an object key segment.
   */
  private const SEGMENT_KEY = 'key';

  /**
   * Identifies an array index segment.
   */
  private const SEGMENT_INDEX = 'index';

  /**
   * Identifies an object wildcard segment.
   */
  private const SEGMENT_WILDCARD_MEMBER = 'wildcard_member';

  /**
   * Identifies an array wildcard segment.
   */
  private const SEGMENT_WILDCARD_ELEMENT = 'wildcard_element';

  /**
   * Constructs a JsonExpression object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param string $field
   *   The column name, optionally table-qualified (e.g. "t.data").
   * @param string $path
   *   The JSONPath expression. Supported formats include dot notation
   *   ($.key, $.nested.path), bracket notation ($["key"]), array indices
   *   ($[0], $.array[1]), and mixed ($.parent["child-key"][0].name).
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path does not match the allowed pattern.
   */
  public function __construct(
    protected readonly Connection $connection,
    string $field,
    string $path,
  ) {
    $this->field = $connection->escapeField($field);

    $this->segments = $this->parsePath($path);
    $this->pathSegments = array_map(
      fn(array $segment) => match ($segment[0]) {
        self::SEGMENT_KEY, self::SEGMENT_INDEX => $segment[1],
        self::SEGMENT_WILDCARD_MEMBER => self::WILDCARD_MEMBER,
        self::SEGMENT_WILDCARD_ELEMENT => self::WILDCARD_ELEMENT,
      },
      $this->segments,
    );
    $this->path = $this->renderSegments($this->segments);
  }

  /**
   * Generates the SQL extraction expression.
   *
   * @return string
   *   A SQL fragment that extracts the value at the given path.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toSql(): string {
    $this->assertNoWildcard('extracting a value');
    return $this->doToSql();
  }

  /**
   * Generates the SQL extraction expression.
   *
   * @return string
   *   A SQL fragment that extracts the value at the given path.
   */
  abstract protected function doToSql(): string;

  /**
   * Generates a SQL expression with a type cast.
   *
   * When called without $expression, extracts and casts the value at this
   * expression's JSON path. When called with $expression, applies the same
   * driver-specific cast to an arbitrary SQL expression (e.g. a wildcard
   * subquery alias like 'jt.val' or 'je.value').
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type.
   * @param string|null $expression
   *   A SQL expression to cast. If NULL, uses toSql() to extract from the
   *   JSON column.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value $expression
   *   yields. SQLite's json_extract() and json_each() return a JSON boolean
   *   as the integer 1 or 0, so the SQLite text cast cannot tell a boolean
   *   from a number by the value alone. It reads the JSON type name from this
   *   expression and renders 'true' or 'false' like the other drivers. Only
   *   needed together with $expression. Without it the type of the value at
   *   the JSON path is used.
   *
   * @return string
   *   A SQL fragment that casts the value to the given type.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard and $expression is NULL.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toCastSql(JsonCastType $type, ?string $expression = NULL, ?string $type_expression = NULL): string {
    if ($expression === NULL) {
      $this->assertNoWildcard('casting a value');
    }
    return $this->doToCastSql($type, $expression, $type_expression);
  }

  /**
   * Generates a SQL expression with a type cast.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type.
   * @param string|null $expression
   *   A SQL expression to cast. If NULL, uses toSql() to extract from the
   *   JSON column.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value $expression
   *   yields, or NULL, as documented on toCastSql().
   *
   * @return string
   *   A SQL fragment that casts the value to the given type.
   */
  abstract protected function doToCastSql(JsonCastType $type, ?string $expression = NULL, ?string $type_expression = NULL): string;

  /**
   * Generates SQL to check if the JSON path exists (has a key/element).
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the path exists.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toHasKeySql(): string {
    $this->assertNoWildcard('testing whether a key exists');
    return $this->doToHasKeySql();
  }

  /**
   * Generates SQL to check if the JSON path exists (has a key/element).
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the path exists.
   */
  abstract protected function doToHasKeySql(): string;

  /**
   * Generates SQL testing whether the JSON value at the path contains another.
   *
   * Containment follows the semantics of PostgreSQL's @> operator and MySQL's
   * JSON_CONTAINS() function. An array is tested against a JSON array and an
   * object against a JSON object.
   *
   * @param string $encoded_value
   *   The JSON-encoded array or object to look for.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the JSON value at the path
   *   contains the given value.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @see \Drupal\Core\Database\Query\JsonCondition::compile()
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toContainsSql(string $encoded_value): string {
    $this->assertNoWildcard('testing containment');
    return $this->doToContainsSql($encoded_value);
  }

  /**
   * Generates SQL testing whether the JSON value at the path contains another.
   *
   * @param string $encoded_value
   *   The JSON-encoded array or object to look for.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if the JSON value at the path
   *   contains the given value.
   */
  abstract protected function doToContainsSql(string $encoded_value): string;

  /**
   * Generates SQL restricting a comparison to values of the expected JSON type.
   *
   * Casting a JSON value to a type it does not hold is not portable. MySQL and
   * MariaDB raise truncation warning 1292, which becomes an error in a
   * statement that writes data, PostgreSQL raises SQLSTATE 22P02, and SQLite
   * silently returns 0. Comparing only the values that already hold the
   * expected type avoids all three, and gives the same result on every
   * backend, because a value of another type equals no value of the compared
   * type.
   *
   * toPredicateSql() only runs the comparison on values that pass this
   * predicate, so a value of another JSON type fails every operator, including
   * a negated one such as <> or NOT BETWEEN. PostgreSQL's jsonpath comparisons
   * behave the same way for scalars. A comparison across JSON types is false,
   * not an error.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type the comparison uses.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value to check. If
   *   NULL, the type of the value at this expression's JSON path is used.
   *
   * @return string
   *   A boolean SQL predicate.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard and $type_expression is NULL.
   *
   * @see https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_truncated_wrong_value
   * @see https://www.postgresql.org/docs/current/errcodes-appendix.html
   * @see https://www.sqlite.org/lang_expr.html#cast_expressions
   * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-SQLJSON-PATH
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string {
    if ($type_expression === NULL) {
      $this->assertNoWildcard('testing the type of a value');
    }
    return $this->doToTypeGuardSql($type, $type_expression);
  }

  /**
   * Generates SQL restricting a comparison to values of the expected JSON type.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type the comparison uses.
   * @param string|null $type_expression
   *   A SQL expression returning the JSON type name of the value to check. If
   *   NULL, the type of the value at this expression's JSON path is used.
   *
   * @return string
   *   A boolean SQL predicate.
   */
  abstract protected function doToTypeGuardSql(JsonCastType $type, ?string $type_expression = NULL): string;

  /**
   * Generates SQL applying a test to the value or values at this path.
   *
   * Without a wildcard the path addresses one value, and the returned SQL
   * tests that value. With a wildcard the path matches any number of values.
   * The driver expands those into a row set, and the returned SQL asks whether
   * any of the matched values satisfies the test.
   *
   * The test is combined with the type guard of $type, so a value of another
   * JSON type is skipped instead of cast.
   *
   * Numeric comparisons use floating-point precision so that JSON integers
   * and floats compare as one numeric type. Values beyond 2^53 may therefore
   * lose precision, including before the query is built if PHP receives them
   * as floats, which happens to an integer literal beyond PHP_INT_MAX. The
   * float comparison applies to conditions only. Select::addJsonExpression()
   * and Select::jsonOrderBy() call toCastSql() directly and keep the integer
   * type.
   *
   * @param \Drupal\Core\Database\Query\JsonCastType $type
   *   The cast type the test compares in.
   * @param callable $test
   *   A callback that receives the SQL expression of one value, cast to $type,
   *   and returns the boolean SQL testing it. For example:
   *   @code
   *   fn(string $value) => "{$value} LIKE :db_condition_placeholder_0"
   *   @endcode
   * @param bool $type_guarded
   *   Whether the test runs only on values of the JSON type matching $type.
   *   CONTAINS, STARTS_WITH and ENDS_WITH pass FALSE, because they search the
   *   text representation of a value of any JSON type.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if a value at the path satisfies
   *   the test.
   *
   * @see https://www.php.net/manual/en/language.types.integer.php#language.types.integer.overflow
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toPredicateSql(JsonCastType $type, callable $test, bool $type_guarded = TRUE): string {
    // Compare JSON integers and floats without truncating fractional values.
    if ($type === JsonCastType::Int) {
      $type = JsonCastType::Float;
    }
    if (!$this->hasWildcard()) {
      return $this->guardedTestSql(
        $type_guarded ? $this->toTypeGuardSql($type) : '',
        $test($this->toCastSql($type)),
      );
    }
    [
      'from' => $from,
      'value' => $value,
      'type' => $type_expression,
      'filter' => $row_filter,
    ] = $this->wildcardRowSet();
    $where = $this->guardedTestSql(
      $type_guarded ? $this->toTypeGuardSql($type, $type_expression) : '',
      $test($this->toCastSql($type, $value, $type_expression)),
    );
    // The value and type expressions can error on a row the filter excludes,
    // so the filter has to run first.
    $where = $this->guardedTestSql($row_filter, $where);
    return $this->wildcardMatchSql($from, $where);
  }

  /**
   * Wraps a test in a CASE so that the guard is evaluated first.
   *
   * SQL does not guarantee evaluation order for AND. Use CASE WHEN to
   * evaluate the guard before the test, avoiding invalid casts on PostgreSQL
   * and extraction from scalar values on SQLite.
   *
   * @param string $guard
   *   A boolean SQL predicate, such as a type guard or a wildcard row filter,
   *   or an empty string for no guard.
   * @param string $test
   *   The boolean SQL to run behind the guard.
   *
   * @return string
   *   The combined boolean SQL.
   *
   * @see https://www.postgresql.org/docs/current/sql-expressions.html#SYNTAX-EXPRESS-EVAL
   */
  protected function guardedTestSql(string $guard, string $test): string {
    return $guard === '' ? $test : "CASE WHEN {$guard} THEN {$test} ELSE FALSE END";
  }

  /**
   * Describes how the driver expands a wildcard path into a row set.
   *
   * Each backend has its own mechanism for iterating the values a wildcard
   * matches: JSON_TABLE on MySQL, json_each on SQLite, jsonb_path_query on
   * PostgreSQL. toPredicateSql() combines the pieces into an EXISTS subquery
   * that is TRUE when any row of the set passes the filter and the test. For
   * example, SQLite expands the path $.tags[*] on the column data into:
   * @code
   * [
   *   'from' => "json_each(data, '$.tags') je",
   *   'value' => 'je.value',
   *   'type' => 'je.type',
   *   'filter' => "json_type(data, '$.tags') = 'array'",
   * ]
   * @endcode
   * and a test of the value against a placeholder becomes:
   * @code
   * EXISTS (SELECT 1 FROM json_each(data, '$.tags') je
   *   WHERE CASE WHEN json_type(data, '$.tags') = 'array'
   *     THEN <the type guard of je.type and the test of je.value>
   *     ELSE FALSE END)
   * @endcode
   *
   * @return array{from: string, value: string, type: string, filter: string}
   *   An array with:
   *   - from: a FROM clause returning one row per matched value.
   *   - value: the SQL expression of the matched value of a row, to be passed
   *     to toCastSql().
   *   - type: a SQL expression returning the JSON type name of that value, to
   *     be passed to toTypeGuardSql().
   *   - filter: a boolean SQL predicate that restricts the rows before the
   *     value is read, or an empty string.
   */
  abstract protected function wildcardRowSet(): array;

  /**
   * Wraps the row set of a wildcard path in a test for a matching row.
   *
   * @param string $from
   *   The FROM clause of the row set, as returned by wildcardRowSet().
   * @param string $where
   *   The condition a row has to satisfy, as built by toPredicateSql() from
   *   the filter of wildcardRowSet(), the type guard and the test.
   *
   * @return string
   *   A SQL fragment that evaluates to TRUE if any row satisfies $where.
   */
  protected function wildcardMatchSql(string $from, string $where): string {
    return "EXISTS (SELECT 1 FROM {$from} WHERE {$where})";
  }

  /**
   * Generates SQL for a comparison against a scalar or array value.
   *
   * For scalar values, produces a simple comparison (e.g. extract = :p).
   * For array values, produces an IN/NOT IN clause. A wildcard path is
   * expanded into a row set by toPredicateSql().
   *
   * @param string $operator
   *   The comparison operator (=, <>, <, >, IN, NOT IN, etc.).
   * @param string|int|float|bool|array $value
   *   The value to compare against. An array for IN/NOT IN operators.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{sql: string, args: array}
   *   An array with 'sql' (the SQL fragment) and 'args' (placeholder values).
   *
   * @internal
   *   This method should only be used by the database layer.
   */
  public function toComparisonSql(string $operator, string|int|float|bool|array $value, PlaceholderInterface $queryPlaceholder): array {
    ['args' => $args, 'cast_type' => $cast_type, 'placeholders' => $placeholders] = $this->buildInPlaceholders((array) $value, $queryPlaceholder);
    return [
      'sql' => $this->toPredicateSql($cast_type, fn(string $extract) => "{$extract} {$operator} ({$placeholders})"),
      'args' => $args,
    ];
  }

  /**
   * Builds the placeholders and the cast type for a comparison value.
   *
   * Works for a single value and for an IN/NOT IN list. A boolean is bound as
   * 1 or 0, because the boolean cast of every driver yields an integer.
   *
   * @param array $value
   *   The values to bind. A single-value comparison passes one.
   * @param \Drupal\Core\Database\Query\PlaceholderInterface $queryPlaceholder
   *   The placeholder interface for generating unique placeholders.
   *
   * @return array{args: array, cast_type: JsonCastType, placeholders: string}
   *   An array with 'args' (placeholder-to-value map), 'cast_type' (the
   *   inferred cast type), and 'placeholders' (comma-separated tokens).
   */
  protected function buildInPlaceholders(array $value, PlaceholderInterface $queryPlaceholder): array {
    $args = [];
    $first_value = $value[array_key_first($value)];
    $cast_to_int = is_bool($first_value);
    foreach ($value as $val) {
      $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
      $args[$placeholder] = $cast_to_int ? (int) $val : $val;
    }
    return [
      'args' => $args,
      'cast_type' => JsonCastType::fromPhpValue($first_value),
      'placeholders' => implode(', ', array_keys($args)),
    ];
  }

  /**
   * Whether this expression's path contains a wildcard (*) segment.
   *
   * @internal
   *   This method should only be used by the database layer and database
   *   drivers.
   */
  public function hasWildcard(): bool {
    return (bool) array_filter($this->segments, $this->isWildcardSegment(...));
  }

  /**
   * Returns the wildcard accessor the path holds, as it appears in the path.
   *
   * The two accessors select different values, so a driver that rebuilds a
   * path around the wildcard has to put back the one the caller wrote. On an
   * array of objects, $.rows[*].uid reads the uid of every row, while
   * $.rows.*.uid reads the members of every row and finds no uid.
   *
   * @return string
   *   ".*" for an object member wildcard, "[*]" for an array element wildcard.
   *   The return value is meaningless when hasWildcard() returns FALSE.
   */
  protected function wildcardAccessor(): string {
    foreach ($this->segments as $segment) {
      if ($segment[0] === self::SEGMENT_WILDCARD_ELEMENT) {
        return self::WILDCARD_ELEMENT;
      }
    }
    return '.' . self::WILDCARD_MEMBER;
  }

  /**
   * Throws when the path holds a wildcard and the caller needs a single value.
   *
   * A wildcard matches any number of values. toPredicateSql() expands it into
   * a row set and asks whether any of the matched values satisfies the test.
   * The other public methods return the SQL of a single value, which has no
   * room for that expansion. Without this check each backend degrades in its
   * own way:
   * PostgreSQL looks for a literal "*" key and returns NULL, because the #>>
   * operator takes the path as an array of keys, SQLite raises an error,
   * because its path syntax has no wildcard, and MySQL returns the whole
   * matched set as a JSON array.
   *
   * @param string $action
   *   What the caller was building, for the exception message. For example
   *   'casting a value'.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path contains a wildcard.
   *
   * @see https://www.postgresql.org/docs/current/functions-json.html
   * @see https://www.sqlite.org/json1.html#path_arguments
   * @see https://dev.mysql.com/doc/refman/8.4/en/json.html#json-path-syntax
   */
  protected function assertNoWildcard(string $action): void {
    if ($this->hasWildcard()) {
      throw new InvalidQueryException(sprintf(
        'The jsonpath "%s" contains a wildcard, which is not supported when %s. A wildcard path is supported in a comparison condition only, with one of the operators: %s.',
        $this->path,
        $action,
        implode(', ', JsonCondition::WILDCARD_OPERATORS),
      ));
    }
  }

  /**
   * Splits the path at the first wildcard into prefix and suffix.
   *
   * For example, $.level1.*.level2.level3 splits into:
   * - prefix: $.level1
   * - suffix: $.level2.level3
   *
   * The accessor itself is dropped. A driver that needs it back in the prefix
   * gets it from wildcardAccessor().
   *
   * @return array{string, string}
   *   A tuple of [prefix, suffix] rendered paths.
   */
  protected function splitAtWildcard(): array {
    foreach ($this->segments as $position => $segment) {
      if ($this->isWildcardSegment($segment)) {
        return [
          $this->renderSegments(array_slice($this->segments, 0, $position)),
          $this->renderSegments(array_slice($this->segments, $position + 1)),
        ];
      }
    }
    throw new \LogicException('The path holds no wildcard.');
  }

  /**
   * Parses a JSONPath expression into segments.
   *
   * Accepts:
   * - Dot notation with keys of word characters and dashes: $.key, $.key-name
   * - Bracket notation with integer indices: $[0], $[42]
   * - Bracket notation with quoted keys, the empty key included: $["any key"],
   *   $["esc\"aped"], $[""]. The only escape sequences are \" and \\. Any
   *   other escape, such as \n, is rejected. The backends do not agree on how
   *   to decode it, so passing it through would read a different key on each
   *   one.
   * - One wildcard: $.*, $[*], $.level1.*.level2
   *
   * @param string $path
   *   The JSONPath expression.
   *
   * @return array[]
   *   The parsed segments, in path order.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If the path cannot be parsed, or holds more than one wildcard.
   */
  protected function parsePath(string $path): array {
    if (!str_starts_with($path, '$')) {
      throw $this->invalidPathException($path);
    }
    $segments = [];
    $wildcards = 0;
    $length = strlen($path);
    $offset = 1;
    // Every token is matched at $offset with the anchored flag, so the whole
    // path has to tokenize. Trailing characters such as a newline fail here.
    $token = '/\.(?<key>[\w-]+)|\["(?<quoted>(?:[^"\\\\]|\\\\["\\\\])*)"]|\[(?<index>\d+)]|\.\*|\[\*]/A';
    while ($offset < $length) {
      if (!preg_match($token, $path, $match, PREG_UNMATCHED_AS_NULL, $offset)) {
        throw $this->invalidPathException($path);
      }
      if (isset($match['key'])) {
        // A key of word characters only is safe to render unquoted. Every
        // other key, such as one with a dash, is double-quoted.
        $segments[] = [self::SEGMENT_KEY, $match['key'], !preg_match('/^\w+$/', $match['key'])];
      }
      elseif (isset($match['quoted'])) {
        // Decode \" and \\. Rendering escapes them again.
        $segments[] = [self::SEGMENT_KEY, preg_replace('/\\\\(["\\\\])/', '$1', $match['quoted']), TRUE];
      }
      elseif (isset($match['index'])) {
        $segments[] = [self::SEGMENT_INDEX, $match['index']];
      }
      else {
        $segments[] = [$match[0] === '[*]' ? self::SEGMENT_WILDCARD_ELEMENT : self::SEGMENT_WILDCARD_MEMBER];
        $wildcards++;
      }
      $offset += strlen($match[0]);
    }
    // Only one wildcard segment is supported. Multiple wildcards would require
    // nested subqueries on SQLite and nested array handling on MySQL.
    if ($wildcards > 1) {
      throw new InvalidQueryException(sprintf(
        'The provided jsonpath "%s" contains multiple wildcards. Only one wildcard segment is supported.',
        $path,
      ));
    }
    return $segments;
  }

  /**
   * Builds the exception for a path the parser rejects.
   *
   * @param string $path
   *   The rejected JSONPath expression.
   *
   * @return \Drupal\Core\Database\InvalidQueryException
   *   The exception to throw.
   */
  protected function invalidPathException(string $path): InvalidQueryException {
    return new InvalidQueryException(sprintf(
      'The provided jsonpath "%s" is invalid. Use dot notation ($.key), bracket notation ($["key"]), or array indices ($[0]).',
      $path,
    ));
  }

  /**
   * Renders parsed segments as a portable JSONPath.
   *
   * A key written in dot notation with word characters only stays unquoted
   * ($.key). Every other key is rendered in double-quoted dot notation with "
   * and \ escaped ($."key-x"). Array indices keep their bracket form ($[0]).
   *
   * The two wildcard accessors select different values, so each is rendered
   * as written. MySQL and PostgreSQL read $.* as every member of an object and
   * $[*] as every element of an array. The SQLite driver reproduces that
   * distinction with a container type filter, because its path syntax has no
   * wildcard.
   *
   * @param array[] $segments
   *   The parsed segments to render.
   *
   * @return string
   *   The rendered JSONPath.
   *
   * @see https://dev.mysql.com/doc/refman/8.4/en/json.html#json-path-syntax
   * @see https://www.postgresql.org/docs/current/functions-json.html#FUNCTIONS-SQLJSON-PATH
   */
  protected function renderSegments(array $segments): string {
    $path = '$';
    foreach ($segments as $segment) {
      $path .= match ($segment[0]) {
        self::SEGMENT_KEY => $segment[2] ? '."' . addcslashes($segment[1], '"\\') . '"' : '.' . $segment[1],
        self::SEGMENT_INDEX => "[{$segment[1]}]",
        self::SEGMENT_WILDCARD_MEMBER => '.' . self::WILDCARD_MEMBER,
        self::SEGMENT_WILDCARD_ELEMENT => self::WILDCARD_ELEMENT,
      };
    }
    return $path;
  }

  /**
   * Whether a parsed segment is a wildcard.
   *
   * @param array $segment
   *   A parsed segment.
   *
   * @return bool
   *   TRUE for a wildcard segment.
   */
  private function isWildcardSegment(array $segment): bool {
    return $segment[0] === self::SEGMENT_WILDCARD_MEMBER || $segment[0] === self::SEGMENT_WILDCARD_ELEMENT;
  }

}
