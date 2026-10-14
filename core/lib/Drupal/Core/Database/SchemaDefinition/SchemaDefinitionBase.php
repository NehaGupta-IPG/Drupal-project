<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Interface for objects describing database elements.
 */
abstract class SchemaDefinitionBase implements SchemaDefinitionInterface {

  /**
   * {@inheritdoc}
   */
  public function with(array $newProperties): static {
    $clone = clone($this, $newProperties);
    return $clone;
  }

  /**
   * {@inheritdoc}
   */
  abstract public function toArray(): array;

}
