<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * Interface for objects describing database elements.
 */
interface SchemaDefinitionInterface {

  /**
   * Returns the key property to be used to normalize array of definitions.
   *
   * Tables in a schema, or columns in a table, stored as simple arrays, do not
   * need to respect the array sequence when compared to compute a diff. This
   * method returns the key to be used for each property to build an
   * associative array for comparison.
   *
   * @return array<string,string>
   *   The legacy array-based specification.
   */
  public function arrayPropertyNormalizationKeys(): array;

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
