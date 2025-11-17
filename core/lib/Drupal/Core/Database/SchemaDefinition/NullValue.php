<?php

declare(strict_types=1);

namespace Drupal\Core\Database\SchemaDefinition;

/**
 * A class holding a null value.
 */
class NullValue {

  /**
   * A null value.
   */
  public readonly null $value;

  public function __construct() {
    $this->value = NULL;
  }

}
