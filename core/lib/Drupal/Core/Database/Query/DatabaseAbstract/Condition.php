<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generic class for a series of conditions in a query.
 */
final class Condition implements \Countable {

  /**
   * Array of conditions.
   *
   * @var array
   */
  public private(set) array $conditions = [];

  /**
   * Constructs a Condition object.
   *
   * @param string $conjunction
   *   The operator to use to combine conditions: 'AND' or 'OR'.
   */
  public function __construct(string $conjunction) {
    $this->conditions['#conjunction'] = $conjunction;
  }

  /**
   * Implements Countable::count().
   *
   * Returns the size of this conditional. The size of the conditional is the
   * size of its conditional array minus one, because one element is the
   * conjunction.
   */
  public function count(): int {
    return count($this->conditions) - 1;
  }

  /**
   * {@inheritdoc}
   */
  public function condition(string|Condition $column, string|int|bool|float|array|Select|null $value = NULL, ?string $operator = '='): static {
    if (empty($operator)) {
      $operator = '=';
    }
    if (empty($value) && is_array($value)) {
      throw new InvalidQueryException(sprintf("Query condition '%s %s ()' cannot be empty.", $column, $operator));
    }
    if (is_array($value) && in_array($operator, ['=', '<', '>', '<=', '>=', 'IS NULL', 'IS NOT NULL'], TRUE)) {
      if (count($value) > 1) {
        $value = implode(', ', $value);
        throw new InvalidQueryException(sprintf("Query condition '%s %s %s' must have an array compatible operator.", $column, $operator, $value));
      }
      else {
        throw new InvalidQueryException('Calling ' . __METHOD__ . '() without an array compatible operator is not supported. See https://www.drupal.org/node/3350985');
      }
    }

    $this->conditions[] = [
      'field' => $column,
      'value' => $value,
      'operator' => $operator,
    ];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function where(string $snippet, array $args = []): static {
    $this->conditions[] = new Expression($snippet, $args);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNull(string $column): static {
    return $this->condition($column, NULL, 'IS NULL');
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull(string $column): static {
    return $this->condition($column, NULL, 'IS NOT NULL');
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
    return $this->where('1 = 0');
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory(string $conjunction = 'AND'): static {
    return new static($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function andConditionGroup(): static {
    return $this->conditionGroupFactory('AND');
  }

  /**
   * {@inheritdoc}
   */
  public function orConditionGroup(): static {
    return $this->conditionGroupFactory('OR');
  }

}
