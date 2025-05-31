<?php

namespace Drupal\Core\Entity\Query;

/**
 * Common code for all implementations of the entity query condition interfaces.
 */
abstract class ConditionFundamentals {

  /**
   * Array of conditions.
   *
   * @var array
   */
  protected $conditions = [];

  /**
   * Constructs a Condition object.
   *
   * @param string $conjunction
   *   The operator to use to combine conditions: 'AND' or 'OR'.
   * @param QueryInterface $query
   *   The entity query this condition belongs to.
   * @param array $namespaces
   *   List of potential namespaces of the classes belonging to this condition.
   */
  public function __construct(
    protected $conjunction,
    protected QueryInterface $query,
    protected $namespaces = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getConjunction() {
    return $this->conjunction;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return count($this->conditions);
  }

  /**
   * {@inheritdoc}
   */
  public function &conditions() {
    return $this->conditions;
  }

  /**
   * Implements the magic __clone function.
   *
   * Makes sure condition groups are cloned as well.
   */
  public function __clone() {
    foreach ($this->conditions as $key => $condition) {
      if ($condition['field'] instanceof ConditionInterface) {
        $this->conditions[$key]['field'] = clone($condition['field']);
      }
    }
  }

}
