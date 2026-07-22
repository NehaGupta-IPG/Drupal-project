<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Statement;

/**
 * Enumeration of the types of possible placeholders in SQL statements.
 */
enum PlaceholderType {

  // Placeholders in the named format ':placeholder'.
  case Named;

  // Placeholders in the positional format '?'.
  case Positional;

}
