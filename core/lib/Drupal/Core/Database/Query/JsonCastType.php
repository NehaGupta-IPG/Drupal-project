<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Query;

/**
 * Cast types for JSON value extraction.
 */
enum JsonCastType: string {

  case Int = 'int';

  case Float = 'float';

  case Bool = 'bool';

  case Text = 'text';

  /**
   * Infers the cast type from a PHP value's type.
   *
   * @param string|int|float|bool $value
   *   The value to infer from. NULL is not accepted; JsonCondition rejects a
   *   NULL comparison value before calling this method.
   *
   * @return self
   *   The matching cast type.
   */
  public static function fromPhpValue(string|int|float|bool $value): self {
    return match (TRUE) {
      is_int($value) => self::Int,
      is_float($value) => self::Float,
      is_bool($value) => self::Bool,
      default => self::Text,
    };
  }

}
