<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * A class holding a scalar value.
 */
class Value {

  public function __construct(public readonly null|int|string|float $value) {
  }

}
