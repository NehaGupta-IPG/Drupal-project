<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

// cSpell:ignore ilike

/**
 * Represents a condition on a JSON-backed field.
 *
 * This class extends Condition so it can be nested inside standard condition
 * groups via $condition->condition($jsonCondition). Driver-specific SQL
 * generation is delegated to the JsonExpression subclasses.
 *
 * Use the jsonCondition() method on Select or other query objects to create
 * instances.
 */
class JsonCondition extends Condition {

  /**
   * The normalized comparison operator.
   */
  protected readonly string $operator;

  /**
   * All supported operators.
   */
  protected const SUPPORTED_OPERATORS = [
    '=', '<>', '!=', '<', '<=', '>', '>=',
    'CONTAINS', 'STARTS_WITH', 'ENDS_WITH',
    'IN', 'NOT IN',
    'BETWEEN', 'NOT BETWEEN',
    'JSON_CONTAINS',
    'IS NULL', 'IS NOT NULL',
    'HAS KEY',
  ];

  /**
   * The operators that accept a path with a wildcard segment.
   *
   * These are the operators that test one extracted value, so the same test
   * can be applied to every value a wildcard matches. The remaining operators
   * read the document at the path itself, which a wildcard does not address.
   *
   * @see \Drupal\Core\Database\Query\JsonExpression::toPredicateSql()
   * @see \Drupal\Core\Database\Query\JsonExpression::assertNoWildcard()
   */
  public const WILDCARD_OPERATORS = [
    '=', '<>', '!=', '<', '<=', '>', '>=',
    'IN', 'NOT IN',
    'CONTAINS', 'STARTS_WITH', 'ENDS_WITH',
    'BETWEEN', 'NOT BETWEEN',
  ];

  /**
   * Constructs a JsonCondition object.
   *
   * @param string $field
   *   The name of the JSON column.
   * @param string $path
   *   The JSONPath expression (must start with '$').
   * @param string|int|float|array|bool|null $value
   *   The comparison value. Unary operators (IS NULL, IS NOT NULL, HAS KEY)
   *   ignore it; every other operator requires it. Its PHP type determines
   *   the comparison domain. See
   *   ConditionInterface::jsonCondition().
   * @param string $operator
   *   The comparison operator.
   */
  public function __construct(
    protected readonly string $field,
    protected readonly string $path,
    protected readonly string|int|float|array|bool|null $value = NULL,
    string $operator = '=',
  ) {
    parent::__construct('AND');
    $this->operator = strtoupper($operator);
    $this->validateOperator();
  }

  /**
   * Validates constructor arguments.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  protected function validateOperator(): void {
    if (!in_array($this->operator, self::SUPPORTED_OPERATORS, TRUE)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s is not supported by %s. Allowed operators include: %s',
        $this->operator,
        __CLASS__,
        implode(', ', self::SUPPORTED_OPERATORS),
      ));
    }
    if (in_array($this->operator, ['IN', 'NOT IN', 'JSON_CONTAINS'], TRUE) && !is_array($this->value)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s requires an array value.',
        $this->operator,
      ));
    }
    if (in_array($this->operator, ['BETWEEN', 'NOT BETWEEN'], TRUE) && (!is_array($this->value) || count($this->value) !== 2)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s requires an array of exactly two values.',
        $this->operator,
      ));
    }
    // Reject arrays before they reach a driver that expects one value.
    $array_operators = [
      'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'JSON_CONTAINS',
      'IS NULL', 'IS NOT NULL', 'HAS KEY',
    ];
    if (is_array($this->value) && !in_array($this->operator, $array_operators, TRUE)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s does not support an array value.',
        $this->operator,
      ));
    }
    // A comparison list holds scalars of one comparison domain, where
    // integers and floats compare as one numeric domain. JSON_CONTAINS is
    // exempt: nested heterogeneous JSON is meaningful there.
    if (in_array($this->operator, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'], TRUE)) {
      $domains = [];
      foreach ($this->value as $element) {
        if (is_array($element)) {
          throw new InvalidQueryException(sprintf(
            'JSON operator %s does not support an array element in its value.',
            $this->operator,
          ));
        }
        if ($element === NULL) {
          throw new InvalidQueryException(sprintf(
            'JSON operator %s does not support a NULL element in its value. Use IS NULL or IS NOT NULL to match a missing value or a stored JSON null.',
            $this->operator,
          ));
        }
        if (!is_scalar($element)) {
          throw new InvalidQueryException(sprintf(
            'JSON operator %s does not support a non-scalar element in its value.',
            $this->operator,
          ));
        }
        $domains[match (TRUE) {
          is_int($element), is_float($element) => 'number',
          is_bool($element) => 'boolean',
          default => 'string',
        }] = TRUE;
      }
      if (count($domains) > 1) {
        throw new InvalidQueryException(sprintf(
          'JSON operator %s requires all values to share one comparison domain (number, boolean or string), got %s.',
          $this->operator,
          implode(' and ', array_keys($domains)),
        ));
      }
    }
    // Only unary operators accept NULL, and they ignore it.
    if ($this->value === NULL && !in_array($this->operator, ['IS NULL', 'IS NOT NULL', 'HAS KEY'], TRUE)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s requires a value. Use IS NULL or IS NOT NULL to match a missing value or a stored JSON null.',
        $this->operator,
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Connection $connection, PlaceholderInterface $queryPlaceholder): void {
    // Re-compile if this condition changed or if we are compiled against a
    // different query placeholder object. The second case gives the condition
    // the placeholder names of that object, instead of keeping the names of
    // an earlier compilation.
    if (!$this->changed && isset($this->queryPlaceholderIdentifier) && $this->queryPlaceholderIdentifier == $queryPlaceholder->uniqueIdentifier()) {
      return;
    }

    $expression = $connection->jsonExpression($this->field, $this->path);

    // A wildcard is expanded into a row set by toPredicateSql() only. The
    // other operators build their SQL from forms that return a single value,
    // so they cannot express one. JsonExpression rejects those forms as well;
    // this check names the operator that reached them.
    if ($expression->hasWildcard() && !in_array($this->operator, self::WILDCARD_OPERATORS, TRUE)) {
      throw new InvalidQueryException(sprintf(
        'JSON operator %s does not support the wildcard in jsonpath "%s". Operators that support a wildcard path: %s.',
        $this->operator,
        $this->path,
        implode(', ', self::WILDCARD_OPERATORS),
      ));
    }

    // Leave the condition recompilable if validation fails.
    $this->queryPlaceholderIdentifier = $queryPlaceholder->uniqueIdentifier();
    $this->changed = FALSE;

    switch ($this->operator) {
      case 'IS NULL':
      case 'IS NOT NULL':
        // Text extraction maps both a missing path and JSON null to SQL NULL.
        $this->stringVersion = "{$expression->toCastSql(JsonCastType::Text)} {$this->operator}";
        $this->arguments = [];
        return;

      case 'HAS KEY':
        $this->stringVersion = $expression->toHasKeySql();
        $this->arguments = [];
        return;

      case 'JSON_CONTAINS':
        // Containment compares the candidate against the value at the path as
        // a whole:
        // - Both have to be of the same JSON type, an array against an array
        //   and an object against an object.
        // - Every element of a candidate array has to be contained in some
        //   element of the value.
        // - Every member of a candidate object has to be present in the value
        //   under the same name, and its value contained.
        // - A scalar is contained when it is equal and of the same JSON type,
        //   except that numbers compare numerically across integer and float.
        // On an array of objects this matches one element as a whole, so it
        // tests a combination of values, where a jsonCondition() per value can
        // match values of different elements. Nesting an array inside a
        // candidate array is not portable, MariaDB compares the elements of
        // those one by one.
        $encoded = json_encode($this->value, JSON_THROW_ON_ERROR);
        $this->stringVersion = $expression->toContainsSql($encoded);
        $this->arguments = [];
        return;

      case 'IN':
      case 'NOT IN':
        // IN with an empty set matches nothing; NOT IN with an empty set
        // matches everything.
        if (empty($this->value)) {
          $this->stringVersion = $this->operator === 'IN' ? '1 = 0' : '1 = 1';
          $this->arguments = [];
          return;
        }
        $result = $expression->toComparisonSql($this->operator, $this->value, $queryPlaceholder);
        $this->stringVersion = $result['sql'];
        $this->arguments = $result['args'];
        return;

      case 'CONTAINS':
      case 'STARTS_WITH':
      case 'ENDS_WITH':
        $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $escaped = $connection->escapeLike((string) $this->value);
        $like_value = match ($this->operator) {
          'CONTAINS' => '%' . $escaped . '%',
          'STARTS_WITH' => $escaped . '%',
          'ENDS_WITH' => '%' . $escaped,
        };
        // Take the driver's mapping of LIKE. PostgreSQL maps it to ILIKE, so
        // that these operators are case-insensitive on every driver, like a
        // regular LIKE condition is.
        $like_operator = $connection->mapConditionOperator('LIKE')
          ?? $this->mapConditionOperator('LIKE');
        $keyword = $like_operator['operator'] ?? 'LIKE';
        $escape = $like_operator['postfix'] ?? '';
        // LIKE searches the text representation of every JSON type.
        $this->stringVersion = $expression->toPredicateSql(
          JsonCastType::Text,
          fn(string $extract) => "{$extract} {$keyword} {$placeholder}{$escape}",
          FALSE,
        );
        $this->arguments = [$placeholder => $like_value];
        return;

      case 'BETWEEN':
      case 'NOT BETWEEN':
        $values = array_values($this->value);
        $type = JsonCastType::fromPhpValue($values[0]);
        $p1 = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $p2 = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $this->stringVersion = $expression->toPredicateSql(
          $type,
          fn(string $extract) => "{$extract} {$this->operator} {$p1} AND {$p2}",
        );
        $this->arguments = [$p1 => $values[0], $p2 => $values[1]];
        return;
    }

    // Scalar and boolean comparisons are handled by the expression, which
    // generates driver-appropriate SQL (extract+placeholder for MySQL/SQLite,
    // jsonpath operators for PgSQL).
    $result = $expression->toComparisonSql($this->operator, $this->value, $queryPlaceholder);
    $this->stringVersion = $result['sql'];
    $this->arguments = $result['args'];
  }

}
