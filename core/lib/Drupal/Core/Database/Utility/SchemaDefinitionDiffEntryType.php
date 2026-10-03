<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Utility;

/**
 * Enumeration of cases for Schema definition diff types.
 */
enum SchemaDefinitionDiffEntryType {

  case Added;
  case Removed;
  case Changed;

}
