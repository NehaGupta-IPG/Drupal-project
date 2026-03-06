<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

/**
 * Cast types for JSON value extraction.
 */
enum JsonCastType: string {

  case Text = 'text';

  case Int = 'int';

  case Float = 'float';

  /**
   * Infers the cast type from a PHP value's type.
   *
   * @param string|int|float $value
   *   The value to infer from.
   *
   * @return self
   *   The matching cast type.
   */
  public static function fromPhpValue(string|int|float $value): self {
    return match (TRUE) {
      is_int($value) => self::Int,
      is_float($value) => self::Float,
      default => self::Text,
    };
  }

}
