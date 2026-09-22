<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query\DatabaseAbstract;

use Drupal\Core\Database\Expression;
use Drupal\Core\Database\InvalidQueryException;

/**
 * Generic class for a series of conditions in a query.
 *
 * @phpstan-type ConditionEntry array{
 *     'variable':string|Condition|Select,
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
   * {@inheritdoc}
   */
  public function condition(string|Condition|Select $variable, Operator|string $operator = Operator::Equal, string|int|bool|float|array|Select|null $value = NULL): static {
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
    return $this->condition($variable, Operator::IsNull);
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull(string|Condition $variable): static {
    return $this->condition($variable, Operator::IsNotNull);
  }

  /**
   * {@inheritdoc}
   */
  public function exists(Select $select): static {
    return $this->condition('', Operator::Exists, $select);
  }

  /**
   * {@inheritdoc}
   */
  public function notExists(Select $select): static {
    return $this->condition('', Operator::NotExists, $select);
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
  public static function and(): static {
    return new static(Conjunction::And);
  }

  /**
   * {@inheritdoc}
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
