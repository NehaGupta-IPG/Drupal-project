<?php

namespace Drupal\entity_test;

/**
 * Build a traversable object.
 */
class TraversableObject implements \IteratorAggregate {

  protected $property1;

  protected $property2;

  /**
   * Constructor.
   */
  public function __construct(array $definition) {
    $this->property1 = $definition['property1'];
    $this->property2 = $definition['property2'];
  }

  /**
   * Convert to array.
   */
  public function toArray() {
    return [
      'property1' => $this->property1,
      'property2' => $this->property2,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    return new \ArrayIterator($this->toArray());
  }

}
