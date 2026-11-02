<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Interface for objects describing database elements.
 */
interface SchemaDefinitionInterface {

  /**
   * Returns a clone of the object with modified properties.
   *
   * @param array<string,mixed> $properties
   *   The modified properties.
   */
  public function with(array $properties): static;

  /**
   * Converts the object to legacy array-based specifications.
   *
   * @return array
   *   The legacy array-based specification.
   *
   * @internal
   */
  public function toArray(): array;

}
