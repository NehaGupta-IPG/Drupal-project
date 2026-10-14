<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Base class for objects describing database elements.
 */
abstract class SchemaDefinitionBase implements SchemaDefinitionInterface {

  /**
   * {@inheritdoc}
   */
  public function with(array $newProperties): static {
    $properties = [];
    foreach (new \ReflectionClass(static::class)->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
      $properties[] = $property->getName();
    }
    $tmp = [];
    foreach ($newProperties as $newProperty => $value) {
      if (in_array($newProperty, $properties, TRUE)) {
        $tmp[$newProperty] = $value;
      }
      else {
        throw new \InvalidArgumentException(get_class($this) . " does not have a '{$newProperty}' property");
      }
    }
    return clone($this, $tmp);
  }

  /**
   * {@inheritdoc}
   */
  abstract public function toArray(): array;

}
