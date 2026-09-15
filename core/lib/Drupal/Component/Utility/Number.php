<?php

namespace Drupal\Component\Utility;

/**
 * Provides helper methods for manipulating numbers.
 *
 * @ingroup utility
 */
class Number {

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
   *   Number::countSignificantDecimals(100.12345678901234567890)
   *   returns 11 but
   *   Number::countSignificantDecimals('100.12345678901234567890')
   *   returns 20.
   */
  private static function countSignificantDecimals(int|float|string $number): int {
    $number_str = (string) $number;

    $exponent = 0;
    // Handle scientific notation (for example, 1.0E-5).
    if (($e_pos = stripos($number_str, 'e')) !== FALSE) {
      $exponent = (int) substr($number_str, $e_pos + 1);
      $number_str = substr($number_str, 0, $e_pos);
    }

    $dot = strpos($number_str, '.');
    $decimals = $dot === FALSE ? 0 : strlen($number_str) - $dot - 1;

    return max(0, $decimals - $exponent);
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
    if ($step <= 0) {
      return FALSE;
    }

    // Convert value to float and check precision.
    $float_value = (float) abs($value - $offset);
    $expected_decimals = self::countSignificantDecimals($step) + 1;
    $actual_decimals = self::countSignificantDecimals($float_value);

    // Reject values with more precision than the step allows.
    if ($actual_decimals > $expected_decimals) {
      return FALSE;
    }

    // Convert to strings to preserve precision.
    $value_str = (string) $value;
    $step_str = (string) $step;
    $offset_str = (string) $offset;

    // Calculate maximum decimals across all inputs to handle trailing zeros.
    $decimals_value = self::countSignificantDecimals($value_str);
    $decimals_step = self::countSignificantDecimals($step_str);
    $decimals_offset = self::countSignificantDecimals($offset_str);
    $max_decimals = max($decimals_value, $decimals_step, $decimals_offset);

    // Scale to integers.
    $multiplier = pow(10, $max_decimals);
    $value_scaled = (float) $value_str * $multiplier;
    $step_scaled = (float) $step_str * $multiplier;
    $offset_scaled = (float) $offset_str * $multiplier;

    // Overflow guard: fallback to float math if exceeding PHP_INT_MAX.
    if (abs($value_scaled) >= PHP_INT_MAX || abs($step_scaled) >= PHP_INT_MAX || abs($offset_scaled) >= PHP_INT_MAX) {
      $double_value = (float) abs($value - $offset);
      $double_step = (float) $step;
      // The fractional part of a double has 53 bits. The greatest number that
      // could be represented with that is 2^53. If the given value is even
      // bigger than $step * 2^53, then dividing by $step will result in a very
      // small remainder. Since that remainder can't even be represented with a
      // single precision float the following computation of the remainder
      // makes no sense and we can safely ignore it instead.
      if ($double_value / pow(2.0, 53) > $double_step) {
        return TRUE;
      }
      // Compute the remainder of a division by $step.
      $remainder = (float) abs($double_value - $double_step * round($double_value / $double_step));
      // Remainders smaller than the acceptable error occur due to rounding
      // when scaling $value and $step to floating point. The fractional
      // part of a float has 24 bits. That means remainders smaller
      // $double_step / 2^24 are acceptable.
      $acceptable_error = $double_step / (2 ** 24);
      return $acceptable_error >= $remainder || $remainder >= ($double_step - $acceptable_error);
    }

    // Integer arithmetic with rounding to handle floating-point drift.
    $value_int = (int) round($value_scaled);
    $step_int = (int) round($step_scaled);
    $offset_int = (int) round($offset_scaled);
    $numerator = abs($value_int - $offset_int);
    $remainder = $numerator % $step_int;

    // Check for exact match.
    if ($remainder === 0) {
      return TRUE;
    }

    // Allow small rounding errors from float scaling.
    $acceptable_error = (float) $step_scaled / (2 ** 24);
    return $remainder <= $acceptable_error || $remainder >= ($step_int - $acceptable_error);
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
