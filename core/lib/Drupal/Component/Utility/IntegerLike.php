<?php

namespace Drupal\Component\Utility;

/**
 * Provides helper methods to cast integer-like values to integer.
 *
 * @ingroup utility
 */
class IntegerLike {

  /**
   * Validates and casts a value as integer.
   *
   * @param mixed $value
   *   A value which may be coming from the database or user input.
   *   If the value is not integer-like, an InvalidArgumentException is thrown.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return int
   *   The integer value.
   *
   * @throws \InvalidArgumentException
   *   The value is not integer-like.
   */
  public static function toInteger(mixed $value, string|\Closure|null $where = NULL): int {
    return match (TRUE) {
      is_int($value) => $value,
      is_string($value) && (string) (int) $value === $value => (int) $value,
      default => throw new \InvalidArgumentException(IntegerLike::expectedButFoundMessage('an integer', $value, $where)),
    };
  }

  /**
   * Casts a value as integer, if possible.
   *
   * @param mixed $value
   *   A value which may be coming from the database or user input.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return mixed
   *   The integer value, or the original value if a clean cast is not possible.
   *
   * @phpstan-return !decimal-int-string
   */
  public static function toIntegerIfPossible(mixed $value, string|\Closure|null $where = NULL): mixed {
    return match (TRUE) {
      is_string($value) && (string) (int) $value === $value => (int) $value,
      default => $value,
    };
  }

  /**
   * Validates and casts a value as integer or NULL.
   *
   * @param mixed $value
   *   A value which may be coming from the database or user input.
   *   If the value is something other than an integer, a stringified integer,
   *   or NULL, an InvalidArgumentException is thrown.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return int|null
   *   The integer value, or NULL.
   *
   * @throws \InvalidArgumentException
   *   The value is not integer-like and not NULL.
   */
  public static function toIntegerOrNull(mixed $value, string|\Closure|null $where = NULL): ?int {
    return match (TRUE) {
      $value === NULL => NULL,
      is_int($value) => $value,
      is_string($value) && (string) (int) $value === $value => (int) $value,
      default => throw new \InvalidArgumentException(IntegerLike::expectedButFoundMessage('an integer or null', $value, $where)),
    };
  }

  /**
   * Validates and casts a value as integer or NULL.
   *
   * This method also accepts '' as NULL, and false/true as 0/1.
   *
   * @param mixed $value
   *   A value which may be coming from the database or user input.
   *   If the value is something other than an integer, a stringified integer,
   *   or NULL, an InvalidArgumentException is thrown.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return int|null
   *   The integer value, or NULL.
   *
   * @throws \InvalidArgumentException
   *   The value is not integer-like, not NULL and not an empty string.
   */
  public static function toIntegerOrNullWeak(mixed $value, string|\Closure|null $where = NULL): ?int {
    return match (TRUE) {
      $value === NULL,
        $value === '' => NULL,
      $value === FALSE => 0,
      $value === TRUE => 1,
      is_int($value) => $value,
      is_string($value) && (string) (int) $value === $value => (int) $value,
      default => throw new \InvalidArgumentException(IntegerLike::expectedButFoundMessage('an integer or null', $value, $where)),
    };
  }

  /**
   * Casts a value as integer or NULL, if possible.
   *
   * @param mixed $value
   *   A value which may be coming from the database or user input.
   *   If the value is not integer-like, an InvalidArgumentException is thrown.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return mixed
   *   The integer value, or NULL, or the original value if a clean-ish cast is
   *   not possible.
   *
   * @phpstan-return !(decimal-int-string|bool|'')
   */
  public static function toIntegerOrNullIfPossible(mixed $value, string|\Closure|null $where = NULL): mixed {
    return match (TRUE) {
      $value === NULL,
      $value === '' => NULL,
      $value === FALSE => 0,
      $value === TRUE => 1,
      is_string($value) && (string) (int) $value === $value => (int) $value,
      default => $value,
    };
  }

  /**
   * Creates a message for a "Expected x, but found y" exception.
   *
   * @param string $expected
   *   A string describing the expected value.
   * @param mixed $value
   *   The actual value.
   * @param string|(\Closure(): string)|null $where
   *   A string describing where the value was meant to be used.
   *
   * @return string
   *   A message to use in an exception.
   */
  protected static function expectedButFoundMessage(string $expected, mixed $value, string|\Closure|null $where): string {
    $value_export = match (TRUE) {
      $value === NULL,
      is_string($value),
      is_float($value),
        $value === [],
      is_bool($value) => var_export($value, TRUE),
      is_object($value) => 'a ' . get_class($value) . ' object',
      is_array($value) => 'an array',
      default => 'a ' . get_debug_type($value) . ' value',
    };
    if ($where instanceof \Closure) {
      $where = $where();
    }
    return sprintf(
      'Expected %s, found %s, for %s.',
      $expected,
      $value_export,
      $where ?? '(not specified)',
    );
  }

}
