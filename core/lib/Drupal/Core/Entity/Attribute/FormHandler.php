<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Attribute;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Attribute class to add form handler properties to entity type definition.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class FormHandler extends EntityTypePropertyBase {

  /**
   * Constructs a FormHandler attribute.
   *
   * @param string $operation
   *   The name of the operation to use, e.g., 'default'.
   * @param class-string $class
   *   The form class implementing \Drupal\Core\Entity\EntityFormInterface.
   */
  public function __construct(
    public readonly string $operation,
    public readonly string $class,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(object|array $definition): EntityTypeInterface {
    if (!($definition instanceof EntityTypeInterface)) {
      throw new \InvalidArgumentException(sprintf('%s attribute can not be used with %s, because it is not an entity type definition.', static::class, $this->getPluginClass()));
    }
    return $definition->setFormClass($this->operation, $this->class);
  }

}
