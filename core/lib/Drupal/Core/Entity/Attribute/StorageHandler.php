<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Attribute class to add storage handler property to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class StorageHandler extends EntityTypeProperty {

  /**
   * Constructs a StorageHandler attribute.
   *
   * @param class-string $class
   *   The class for the entity type's storage.
   */
  public function __construct(public readonly string $class) {
    parent::__construct(['handlers', 'storage'], $class);
  }

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(object|array $definition): EntityTypeInterface {
    if (!($definition instanceof EntityTypeInterface)) {
      throw new \InvalidArgumentException(sprintf('%s attribute can not be used with %s, because it is not an entity type definition.', static::class, $this->getPluginClass()));
    }
    return $definition->setStorageClass($this->class);
  }

}
