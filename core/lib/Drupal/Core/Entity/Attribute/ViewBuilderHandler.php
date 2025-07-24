<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Attribute class to add view builder class to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ViewBuilderHandler extends EntityTypeProperty {

  /**
   * Constructs a ViewBuilderHandler attribute.
   *
   * @param class-string $class
   *   The class for this entity type's view builder.
   */
  public function __construct(public readonly string $class) {
    parent::__construct(['handlers', 'view_builder'], $this->class);
  }

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(object|array $definition): EntityTypeInterface {
    if (!($definition instanceof EntityTypeInterface)) {
      throw new \InvalidArgumentException(sprintf('%s attribute can not be used with %s, because it is not an entity type definition.', static::class, $this->getPluginClass()));
    }
    return $definition->setViewBuilderClass($this->class);
  }

}
