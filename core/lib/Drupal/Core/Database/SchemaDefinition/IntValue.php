<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * A class holding an int value.
 */
class IntValue {

  public function __construct(public readonly int $value) {
  }

}
