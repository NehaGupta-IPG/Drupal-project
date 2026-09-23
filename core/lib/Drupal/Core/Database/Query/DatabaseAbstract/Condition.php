<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generic class for a series of conditions in a query.
 *
 * @phpstan-type ConditionEntry array{
 *     'variable':string|Condition|Select|null,
 *     'operator':Operator,
 *     'value':string|int|bool|float|array|Select|null,
 *   }|Expression
 *
 * @property-read list<ConditionEntry> $conditions
 */
final class Condition {

  /**
   * Array of conditions.
   *
   * @var list<ConditionEntry>
   */
  private array $conditions = [];

  /**
   * Constructs a Condition object.
   *
   * @param Conjunction $conjunction
   *   The operator to use to combine conditions.
   */
  public function __construct(
    public readonly Conjunction $conjunction,
  ) {
  }

  /**
   * Builds the most common conditional clauses.
   *
   * @param string|Condition|Select|null $variable
   *   The name of the column to check. This can also be a Condition in itself
   *   or a Select object.
   * @param Operator|string $operator
   *   The operator to use:
   *   - The comparison operators =, <>, <, <=, >, >=.
   *   - The operators (NOT) BETWEEN, (NOT) IN, (NOT) EXISTS, (NOT) LIKE,
   *     (NOT) REGEXP.
   * @param string|int|bool|float|array|Select|null $value
   *   The value to test the field against. In most cases, and depending on the
   *   operator, this will be a scalar or an array. As SQL accepts select
   *   queries on any place where a scalar value or set is expected, $value may
   *   also be a Select or an array of Select. If $operator is a unary
   *   operator, e.g. IS NULL, $value will be ignored and should be null. If
   *   the operator requires a subquery, e.g. EXISTS, the $variable will be
   *   ignored and $value should be a Select object.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   *   If passed invalid arguments, such as an empty array as $value.
   */
  public function condition(string|Condition|Select|null $variable, Operator|string $operator = Operator::Equal, string|int|bool|float|array|Select|null $value = NULL): static {
    if (is_string($operator)) {
      $operator = Operator::from($operator);
    }
    if (empty($value) && is_array($value) && is_string($variable)) {
      throw new InvalidQueryException(sprintf("Query condition '%s %s ()' cannot be empty.", $variable, $operator->value));
    }
    if (
        is_array($value) &&
        in_array($operator->value, ['=', '<', '>', '<=', '>=', 'IS NULL', 'IS NOT NULL'], TRUE) &&
        is_string($variable)
      ) {
      if (count($value) > 1) {
        $onlyStrings = array_map(function (mixed $v): string {
          return match (gettype($v)) {
            'string' => $v,
            'integer', 'double' => (string) $v,
            'boolean' => $v ? 'TRUE' : 'FALSE',
            'NULL' => 'NULL',
            'array' => '[array value]',
            'object' => '[' . get_class($v) . ' value]',
            'resource', 'resource (closed)' => '[resource value]',
            default => '[unknown type value]',
          };
        }, $value);
        throw new InvalidQueryException(sprintf("Query condition '%s %s %s' must have an array compatible operator.", $variable, $operator->value, implode(', ', $onlyStrings)));
      }
      else {
        throw new InvalidQueryException('Calling ' . __METHOD__ . '() without an array compatible operator is not supported. See https://www.drupal.org/node/3350985');
      }
    }

    $this->conditions[] = [
      'variable' => $variable,
      'operator' => $operator,
      'value' => $value,
    ];

    return $this;
  }

  /**
   * Adds an arbitrary WHERE clause to the query.
   *
   * @param Expression $expression
   *   A portion of a WHERE clause as a prepared statement.
   */
  public function where(Expression $expression): static {
    $this->conditions[] = $expression;
    return $this;
  }

  /**
   * Sets a condition that the specified column be NULL.
   *
   * @param string|Condition $variable
   *   The name of the column or a condition to check.
   */
  public function isNull(string|Condition $variable): static {
    return $this->condition($variable, Operator::IsNull);
  }

  /**
   * Sets a condition that the specified column be NOT NULL.
   *
   * @param string|Condition $variable
   *   The name of the column or a condition to check.
   */
  public function isNotNull(string|Condition $variable): static {
    return $this->condition($variable, Operator::IsNotNull);
  }

  /**
   * Sets a condition that the specified subquery returns values.
   *
   * @param Select $select
   *   The subquery that must contain results.
   */
  public function exists(Select $select): static {
    return $this->condition(NULL, Operator::Exists, $select);
  }

  /**
   * Sets a condition that the specified subquery returns no values.
   *
   * @param Select $select
   *   The subquery that must not contain results.
   */
  public function notExists(Select $select): static {
    return $this->condition(NULL, Operator::NotExists, $select);
  }

  /**
   * Sets a condition that is always false.
   */
  public function alwaysFalse(): static {
    return $this->where(new Expression('1 = 0'));
  }

  /**
   * Creates a new condition to AND a group of conditions together.
   */
  public static function and(): static {
    return new static(Conjunction::And);
  }

  /**
   * Creates a new condition to OR a group of conditions together.
   */
  public static function or(): static {
    return new static(Conjunction::Or);
  }

  /**
   * {@inheritdoc}
   */
  public function __get(string $name): mixed {
    if (in_array($name, array_keys(get_object_vars($this), TRUE)) && is_array($this->{$name})) {
      return $this->{$name};
    }
    throw new \LogicException('Unavailable property $' . $name . ' for ' . __CLASS__);
  }

}
