<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generic class for a series of conditions in a query.
 */
final class Condition {

  /**
   * Array of conditions.
   *
   * @var list<array{
   *     'variable':string|Condition|Select,
   *     'operator':string,
   *     'value':string|int|bool|float|array|Select|null,
   *   }|Expression>
   */
  public private(set) array $conditions = [];

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
   * {@inheritdoc}
   */
  public function condition(string|Condition|Select $variable, string|int|bool|float|array|Select|null $value = NULL, ?string $operator = '='): static {
    if (empty($operator)) {
      $operator = '=';
    }
    if (empty($value) && is_array($value) && is_string($variable)) {
      throw new InvalidQueryException(sprintf("Query condition '%s %s ()' cannot be empty.", $variable, $operator));
    }
    if (
        is_array($value) &&
        in_array($operator, ['=', '<', '>', '<=', '>=', 'IS NULL', 'IS NOT NULL'], TRUE) &&
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
        throw new InvalidQueryException(sprintf("Query condition '%s %s %s' must have an array compatible operator.", $variable, $operator, implode(', ', $onlyStrings)));
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
   * {@inheritdoc}
   */
  public function where(Expression $expression): static {
    $this->conditions[] = $expression;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNull(string|Condition $variable): static {
    return $this->condition($variable, NULL, 'IS NULL');
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull(string|Condition $variable): static {
    return $this->condition($variable, NULL, 'IS NOT NULL');
  }

  /**
   * {@inheritdoc}
   */
  public function exists(Select $select): static {
    return $this->condition('', $select, 'EXISTS');
  }

  /**
   * {@inheritdoc}
   */
  public function notExists(Select $select): static {
    return $this->condition('', $select, 'NOT EXISTS');
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysFalse(): static {
    return $this->where(new Expression('1 = 0'));
  }

  /**
   * {@inheritdoc}
   */
  private function conditionGroupFactory(Conjunction $conjunction): static {
    return new static($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function andConditionGroup(): static {
    return $this->conditionGroupFactory(Conjunction::And);
  }

  /**
   * {@inheritdoc}
   */
  public function orConditionGroup(): static {
    return $this->conditionGroupFactory(Conjunction::Or);
  }

}
