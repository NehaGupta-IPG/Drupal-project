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
      // If the float has fewer significant decimals than the PHP runtime
      // configuration guarantees, convert it to a string directly.
      if (preg_match('/^\d+\.\d+$/', (string) $number)) {
        return (string) $number;
      }
      // Discard digits beyond what can be rounded to float and back.
      return rtrim(number_format($number, PHP_FLOAT_DIG, '.', ''), '0');
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
   *   Number::countSignificantDecimals(100.12345678901234567890)
   *   returns 11 but
   *   Number::countSignificantDecimals('100.12345678901234567890')
   *   returns 20.
   */
  private static function countSignificantDecimals(int|float|string $number): int {
    $number_str = (string) $number;

    // Handle scientific notation (for example, 1.0E-5).
    if (stripos($number_str, 'e') !== FALSE) {
      [$mantissa, $exponent] = preg_split('/e/i', $number_str);
      $mantissa_decimals = 0;
      if (strpos($mantissa, '.') !== FALSE) {
        $mantissa_decimals = strlen(substr(strrchr($mantissa, '.'), 1));
      }
      $exp_val = (int) $exponent;
      return $exp_val < 0 ? $mantissa_decimals + abs($exp_val) : max(0, $mantissa_decimals - $exp_val);
    }

    // Standard decimal handling.
    if (strpos($number_str, '.') !== FALSE) {
      return strlen(substr(strrchr($number_str, '.'), 1));
    }

    return 0;
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
      $double_value = (float) $value;
      $double_step = (float) $step;
      $double_offset = (float) $offset;
      $n = round(($double_value - $double_offset) / $double_step);
      $calculated_value = $double_offset + ($n * $double_step);
      return abs($double_value - $calculated_value) < 1E-10;
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
    // For backwards compatibility, we must accept NULL
    // and the empty string, returning 0,
    // like (int) base_convert(substr($string, 1), 36, 10) always did.
    if ('' === $string || NULL === $string) {
      @trigger_error('Passing NULL or an empty string to ' . __METHOD__ . '() is deprecated in drupal:11.2.0 and will be removed in drupal:12.0.0. See https://www.drupal.org/node/3494472', E_USER_DEPRECATED);
      return 0;
    }
    $alpha_decimal_substring = substr($string, 1);
    if (!ctype_alnum($alpha_decimal_substring)) {
      throw new \InvalidArgumentException("Invalid characters passed for attempted conversion: $string");
    }
    return (int) base_convert($alpha_decimal_substring, 36, 10);
  }

}
