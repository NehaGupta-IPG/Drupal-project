<?php

namespace Drupal\Component\Utility;

/**
 * Provides helper methods for manipulating numbers.
 *
 * @ingroup utility
 */
class Number {

  /**
   * Normalizes a numeric value to a lossless, easily parseable numeric string.
   *
   * The normalized value is a string suitable for use with libraries such as
   * BCMath.
   *
   * @param int|float|string $number
   *   The value to normalize. If its a string, it must be formatted as an
   *   integer or float. Float with a higher number of significant decimals
   *   than the precision value from the PHP runtime configuration
   *   [default: 14] will lose the additional precision as PHP does not
   *   guarantee that precision value.
   *
   * @see https://secure.php.net/manual/en/book.bc.php
   *
   * @return string
   *   The normalized numeric string.
   */
  private static function normalize(int|float|string $number): string {
    if (is_float($number)) {
      // If the float has less significant decimals than the number we can
      // guarantee, convert it to a string directly.
      if (preg_match('/^\d+\.\d+$/', (string) $number)) {
        return (string) $number;
      }
      // For floats with more significant decimals than 100, discard.
      return rtrim(number_format($number, 15, '.', ''), '0');
    }
    return (string) $number;
  }

  /**
   * Counts a number's significant decimals.
   *
   * @param int|float|string $number
   *   The number whose decimals needed to be to counted. If this is a string,
   *   it must be an integer or formatted as a float. Floats are limited to the
   *   precision guaranteed by PHP (for example, 15).
   *
   * @return int
   *   The number of significant decimal digits. Floats are limited to the
   *   precision guaranteed by PHP (for example, 15). Numeric strings do not
   *   suffer from system-specific limitations to float precision, so they
   *   can contain more significant decimals.
   *
   *   @code Number::countSignificantDecimals(100.12345678901234567890) @endcode
   *   returns 11 but
   *   @code Number::countSignificantDecimals('100.12345678901234567890') @endcode
   *   returns 20.
   */
  private static function countSignificantDecimals(int|float|string $number): int {
    $number = is_numeric($number) ? static::normalize($number) : 0;

    // If no decimal separator is encountered, there are 0 significant
    // decimals.
    if (!str_contains($number, '.')) {
      return 0;
    }

    // If a decimal separator is encountered, count the number of significant
    // decimal digits.
    return strlen($number) - strrpos($number, '.') - 1;
  }

  /**
   * Verifies that a number is a multiple of a given step.
   *
   * The implementation assumes it is dealing with IEEE 754 double precision
   * floating point numbers that are used by PHP on most systems.
   *
   * This is based on the number/range verification methods of webkit.
   *
   * Besides integers and floating numbers, we also support decimal numbers
   * which are not stored in IEEE 754 format. In somewhat higher precisions for
   * these numbers, the $step value cannot accurately represent the desired
   * precision, when it is passed as a float. Passing it as a string bypasses
   * this loss of precision and enables a correct calculation of the step
   * validity.
   *
   * @param int|float|string $value
   *   The value that needs to be checked. If this is a string, it must be
   *   formatted as an integer or a float.
   * @param int|float|string $step
   *   The step scale factor. If this is a string, it must be formatted as an
   *   integer or a float.
   * @param float|null $offset
   *   (optional) An offset, to which the difference must be a multiple of the
   *   given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occurred, or FALSE otherwise.
   *
   * @see http://opensource.apple.com/source/WebCore/WebCore-1298/html/NumberInputType.cpp
   */
  public static function validStep($value, $step, $offset = 0.0) {
    // Confirm the step is positive.
    if ($step <= 0) {
      return FALSE;
    }

    // Convert the value to a float so we can evaluate the precision later.
    // Because subtracting the offset may change the value's precision, we only
    // do so if it was set explicitly (is not null).
    $float_value = (float) abs($value - $offset);

    // The step dictates the expected number of significant decimals.
    $expected_significant_decimals = static::countSignificantDecimals($step) + 1;

    // If the actual value has more significant decimals than expected, then it
    // has a higher precision than desired, so it isn't divisible by the step.
    $actual_significant_decimals = static::countSignificantDecimals($float_value);
    if ($actual_significant_decimals > $expected_significant_decimals) {
      return FALSE;
    }

    $float_value = (float) round($float_value, $expected_significant_decimals);

    // The fractional part of a double has 53 bits. The greatest number that
    // could be represented with that is 2^53. If the given value is even bigger
    // than $step * 2^53, then dividing by $step will result in a very small
    // remainder. Since that remainder can't even be represented with a single
    // precision float the following computation of the remainder makes no sense
    // and we can safely ignore it instead.
    if ($float_value / (2 ** 53) > $step) {
      return TRUE;
    }

    $expected_float_value = (float) round($step * round($float_value / $step), $expected_significant_decimals);
    // Now compute that remainder of a division by $step.
    $remainder = (float) abs($float_value - $expected_float_value);

    // $remainder is a double precision floating point number. Remainders that
    // can't be represented with single precision floats are acceptable. The
    // fractional part of a float has 24 bits. That means remainders smaller
    // than $step * 2^-24 are acceptable.
    $computed_acceptable_error = (float) ($step / (2 ** 24));

    return $computed_acceptable_error >= $remainder || $remainder >= ($step - $computed_acceptable_error);
  }

  /**
   * Generates a sorting code from an integer.
   *
   * Consists of a leading character indicating length, followed by N digits
   * with a numerical value in base 36 (alphadecimal). These codes can be sorted
   * as strings without altering numerical order.
   *
   * It goes:
   * 00, 01, 02, ..., 0y, 0z,
   * 110, 111, ... , 1zy, 1zz,
   * 2100, 2101, ..., 2zzy, 2zzz,
   * 31000, 31001, and so on.
   *
   * @param int $i
   *   The integer value to convert.
   *
   * @return string
   *   The alpha decimal value.
   *
   * @see \Drupal\Component\Utility\Number::alphadecimalToInt
   */
  public static function intToAlphadecimal($i = 0) {
    $num = base_convert((string) $i, 10, 36);
    $length = strlen($num);

    return chr($length + ord('0') - 1) . $num;
  }

  /**
   * Decodes a sorting code back to an integer.
   *
   * @param string $string
   *   The alpha decimal value to convert.
   *
   * @return int
   *   The integer value.
   *
   * @throws \InvalidArgumentException
   *   If $string contains invalid characters, throw an exception.
   *
   * @see \Drupal\Component\Utility\Number::intToAlphadecimal
   */
  public static function alphadecimalToInt($string = '00') {
    $alpha_decimal_substring = substr($string, 1);
    if (!ctype_alnum($alpha_decimal_substring)) {
      throw new \InvalidArgumentException("Invalid characters passed for attempted conversion: $string");
    }
    return (int) base_convert($alpha_decimal_substring, 36, 10);
  }

}
