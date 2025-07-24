<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

/**
 * Attribute class to add a single constraint to an entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Constraint extends EntityTypeProperty {

  /**
   * Constructs a Constraint attribute.
   *
   * @param string $name
   *   The validation constraint name (plugin ID).
   * @param array $options
   *   Options to provide to the constraint definition.
   */
  public function __construct(
    public readonly string $name,
    public readonly array $options = [],
  ) {
    parent::__construct(['constraints', $name], $options);
  }

}
