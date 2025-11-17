<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * A class holding a mixed value.
 */
class Value {

  public function __construct(public readonly mixed $value) {
  }

}
