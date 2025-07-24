<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Attribute class to add list builder class to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ListBuilderHandler extends EntityTypePropertyBase {

  /**
   * Constructs a ListBuilderHandler attribute.
   *
   * @param class-string $class
   *   The list class to use for the entity type.
   */
  public function __construct(public readonly string $class) {}

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(object|array $definition): EntityTypeInterface {
    if (!($definition instanceof EntityTypeInterface)) {
      throw new \InvalidArgumentException(sprintf('%s attribute can not be used with %s, because it is not an entity type definition.', static::class, $this->getPluginClass()));
    }
    return $definition->setListBuilderClass($this->class);
  }

}
