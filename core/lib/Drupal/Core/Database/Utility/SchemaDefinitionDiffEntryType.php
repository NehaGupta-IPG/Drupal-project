<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

/**
 * Enumeration of cases for Schema definition diff types.
 */
enum SchemaDefinitionDiffEntryType {

  // The old property was NULL, the new one has a value assigned.
  case Set;
  // The old and the new property values are different.
  case Changed;
  // The old property had a value, the new one is NULL.
  case Unset;

  // A key is added to an array property.
  case Added;
  // A key is removed from an array property.
  case Removed;

}
