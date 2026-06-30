<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * A class holding a string value.
 */
class StringValue {

  public function __construct(public readonly string $value) {
  }

}
