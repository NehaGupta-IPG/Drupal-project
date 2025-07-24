<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

/**
 * Attribute class to add a single entity key to an entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class EntityKey extends EntityTypeProperty {

  /**
   * Constructs a EntityKey attribute.
   *
   * @param string $key
   *   The name of the entity key, such as 'id', 'revision', etc.
   * @param string $property
   *   The property on the entity type that contains the value that referenced
   *   by the entity key. Defaults to be the same as $key.
   *
   * @see \Drupal\Core\Entity\EntityTypeInterface::getKeys()
   */
  public function __construct(
    string $key,
    public readonly string $property = '',
  ) {
    parent::__construct(['entity_keys', $key], $property ?: $key);
  }

}
