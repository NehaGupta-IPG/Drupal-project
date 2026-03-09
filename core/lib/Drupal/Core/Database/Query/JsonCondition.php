<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\InvalidQueryException;

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
   * Constructs a JsonCondition object.
   *
   * @param string $field
   *   The name of the JSON column.
   * @param string $path
   *   The JSONPath expression (must start with '$').
   * @param string|int|float|array|bool|null $value
   *   The value to compare against. Ignored for unary operators (IS NULL,
   *   IS NOT NULL, HAS KEY).
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
    $this->validateArguments();
  }

  /**
   * Validates constructor arguments.
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  protected function validateArguments(): void {
    if (!in_array($this->operator, self::SUPPORTED_OPERATORS, TRUE)) {
      throw new InvalidQueryException(sprintf(
        'Operator %s is not supported by %s. Allowed operators include: %s',
        $this->operator,
        __CLASS__,
        implode(', ', self::SUPPORTED_OPERATORS),
      ));
    }
    if (in_array($this->operator, ['IN', 'NOT IN', 'JSON_CONTAINS'], TRUE) && !is_array($this->value)) {
      throw new InvalidQueryException(sprintf(
        'Operator %s requires an array value.',
        $this->operator,
      ));
    }
    if (in_array($this->operator, ['BETWEEN', 'NOT BETWEEN'], TRUE) && (!is_array($this->value) || count($this->value) !== 2)) {
      throw new InvalidQueryException(sprintf(
        'Operator %s requires an array of exactly two values.',
        $this->operator,
      ));
    }
    if (!preg_match('/^\$(\.\w+|\[\d+\])*$/', $this->path)) {
      throw new InvalidQueryException(sprintf(
        'The provided jsonpath "%s" is invalid. Supported jsonpath expressions must start with "$" and use dot notation ($.key) or bracket notation ($[0]) with alphanumeric identifiers.',
        $this->path,
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function usesStrictParameters(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function compile(Connection $connection, PlaceholderInterface $queryPlaceholder): void {
    if (!$this->changed) {
      return;
    }
    $this->changed = FALSE;

    $expression = $connection->jsonExpression($this->field, $this->path);

    // All operators are handled generically via the driver-specific
    // JsonExpression methods: toSql(), toCastSql(), toHasKeySql(),
    // toContainsSql(), and toComparisonSql().
    switch ($this->operator) {
      case 'IS NULL':
      case 'IS NOT NULL':
        $this->stringVersion = "{$expression->toSql()} {$this->operator}";
        $this->arguments = [];
        return;

      case 'HAS KEY':
        $this->stringVersion = $expression->toHasKeySql();
        $this->arguments = [];
        return;

      case 'JSON_CONTAINS':
        $encoded = json_encode($this->value, JSON_THROW_ON_ERROR);
        $this->stringVersion = $expression->toContainsSql($connection, $encoded);
        $this->arguments = [];
        return;

      case 'IN':
      case 'NOT IN':
        $this->arguments = [];
        foreach ($this->value as $val) {
          $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
          $this->arguments[$placeholder] = $val;
        }
        $castType = JsonCastType::fromPhpValue($this->value[array_key_first($this->value)]);
        $this->stringVersion = "{$expression->toCastSql($castType)} {$this->operator} (" . implode(', ', array_keys($this->arguments)) . ')';
        return;

      case 'CONTAINS':
      case 'STARTS_WITH':
      case 'ENDS_WITH':
        $placeholder = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $escaped = $connection->escapeLike((string) $this->value);
        $likeValue = match ($this->operator) {
          'CONTAINS' => '%' . $escaped . '%',
          'STARTS_WITH' => $escaped . '%',
          'ENDS_WITH' => '%' . $escaped,
        };
        $likeOperator = $connection->mapConditionOperator('LIKE')
          ?? $this->mapConditionOperator('LIKE');
        $escape = $likeOperator['postfix'] ?? '';
        $this->stringVersion = "{$expression->toCastSql(JsonCastType::Text)} LIKE {$placeholder}{$escape}";
        $this->arguments = [$placeholder => $likeValue];
        return;

      case 'BETWEEN':
      case 'NOT BETWEEN':
        $values = array_values($this->value);
        $type = JsonCastType::fromPhpValue($values[0]);
        $p1 = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $p2 = ':db_condition_placeholder_' . $queryPlaceholder->nextPlaceholder();
        $this->stringVersion = "{$expression->toCastSql($type)} {$this->operator} {$p1} AND {$p2}";
        $this->arguments = [$p1 => $values[0], $p2 => $values[1]];
        return;
    }

    // Scalar and boolean comparisons are handled by the expression, which
    // generates driver-appropriate SQL (extract+placeholder for MySQL/SQLite,
    // jsonpath operators for PgSQL).
    $result = $expression->toComparisonSql($this->operator, $this->value, $connection, $queryPlaceholder);
    $this->stringVersion = $result['sql'];
    $this->arguments = $result['args'];
  }

}
