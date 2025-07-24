<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Attribute class to add an access class to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AccessHandler extends EntityTypePropertyBase {

  /**
   * Constructs an AccessHandler attribute.
   *
   * @param class-string $class
   *   The class for this entity type's access control handler.
   */
  public function __construct(public readonly string $class) {}

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(object|array $definition): EntityTypeInterface {
    if (!($definition instanceof EntityTypeInterface)) {
      throw new \InvalidArgumentException(sprintf('%s attribute can not be used with %s, because it is not an entity type definition.', static::class, $this->getPluginClass()));
    }
    return $definition->setAccessClass($this->class);
  }

}
