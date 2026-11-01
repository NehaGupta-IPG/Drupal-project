<?php

namespace Drupal\Component\Utility;

/**
 * Provides helper methods for manipulating numbers.
 *
 * @ingroup utility
 */
class Number {

  /**
   * The largest difference in decimal exponents compared as exact decimals.
   *
   * Larger differences fall back to floating point math, to avoid building
   * very long digit strings for values such as '1e999999'.
   */
  private const MAX_EXPONENT_RANGE = 400;

  /**
   * Verifies that a number is a multiple of a given step.
   *
   * The value, step and offset are compared as exact decimal numbers, so the
   * result does not suffer from rounding errors that are inherent to IEEE 754
   * floating point numbers. For example, 4444444 is a valid multiple of 0.01.
   *
   * Numeric strings are compared with all of their digits, so they can be
   * more precise than floats. Floats are converted to decimals using the
   * precision guaranteed by PHP (PHP_FLOAT_DIG significant digits). If a
   * float cannot be represented exactly with that precision (for example
   * 1 / 3) and the exact comparison fails, floating point math with a small
   * tolerance is used instead. This is based on the number/range verification
   * methods of webkit.
   *
   * @param int|float|string $value
   *   The value that needs to be checked. If this is a string, it must be
   *   formatted as an integer or a float.
   * @param int|float|string $step
   *   The step scale factor. Must be positive. If this is a string, it must be
   *   formatted as an integer or a float.
   * @param int|float|string $offset
   *   (optional) An offset, to which the difference must be a multiple of the
   *   given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occurred, or FALSE otherwise.
   *
   * @see http://opensource.apple.com/source/WebCore/WebCore-1298/html/NumberInputType.cpp
   */
  public static function validStep($value, $step, $offset = 0.0) {
    $decimal_value = static::parseDecimal($value);
    $decimal_step = static::parseDecimal($step);
    $decimal_offset = static::parseDecimal($offset);

    if ($decimal_value === NULL || $decimal_step === NULL || $decimal_offset === NULL) {
      return $step > 0 && static::validStepFloat($value, $step, $offset);
    }
    if ($decimal_step['sign'] <= 0) {
      return FALSE;
    }

    $valid = static::isExactMultiple($decimal_value, $decimal_step, $decimal_offset);
    if ($valid === TRUE) {
      return TRUE;
    }

    // An exact mismatch is final, unless a float could not be converted to a
    // decimal without losing precision.
    if ($valid === FALSE && $decimal_value['exact'] && $decimal_step['exact'] && $decimal_offset['exact']) {
      return FALSE;
    }
    return static::validStepFloat($value, $step, $offset);
  }

  /**
   * Verifies that a number is a multiple of a step using floating point math.
   *
   * The implementation assumes it is dealing with IEEE 754 double precision
   * floating point numbers that are used by PHP on most systems.
   *
   * @param int|float|string $value
   *   The value that needs to be checked.
   * @param int|float|string $step
   *   The step scale factor. Must be positive.
   * @param int|float|string $offset
   *   An offset, to which the difference must be a multiple of the given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occurred, or FALSE otherwise.
   */
  private static function validStepFloat($value, $step, $offset): bool {
    $double_value = (float) abs($value - $offset);
    $double_step = (float) $step;

    // The fractional part of a double has 53 bits. The greatest number that
    // could be represented with that is 2^53. If the given value is even bigger
    // than $step * 2^53, then dividing by $step will result in a very small
    // remainder. Since that remainder can't even be represented with a single
    // precision float the following computation of the remainder makes no sense
    // and we can safely ignore it instead.
    if ($double_value / (2 ** 53) > $double_step) {
      return TRUE;
    }

    // Now compute that remainder of a division by $step.
    $remainder = (float) abs($double_value - $double_step * round($double_value / $double_step));

    // $remainder is a double precision floating point number. Remainders that
    // can't be represented with single precision floats are acceptable. The
    // fractional part of a float has 24 bits. That means remainders smaller
    // than $step * 2^-24 are acceptable.
    $acceptable_error = $double_step / (2 ** 24);

    return $acceptable_error >= $remainder || $remainder >= ($double_step - $acceptable_error);
  }

  /**
   * Checks if the difference of two decimals is a multiple of a step.
   *
   * @param array $value
   *   The value, as returned by static::parseDecimal().
   * @param array $step
   *   The positive step, as returned by static::parseDecimal().
   * @param array $offset
   *   The offset, as returned by static::parseDecimal().
   *
   * @return bool|null
   *   TRUE if the difference between value and offset is a multiple of step,
   *   FALSE if it is not, or NULL if this cannot be computed exactly.
   */
  private static function isExactMultiple(array $value, array $step, array $offset): ?bool {
    // Calculate the absolute difference between the value and the offset as
    // a string of digits, multiplied by 10 to the power of an exponent.
    if ($offset['sign'] === 0 || $value['sign'] === 0) {
      $difference = $offset['sign'] === 0 ? $value : $offset;
      $digits = $difference['digits'];
      $exponent = $difference['exponent'];
    }
    else {
      $exponent = min($value['exponent'], $offset['exponent']);
      if (max($value['exponent'], $offset['exponent']) - $exponent > self::MAX_EXPONENT_RANGE) {
        return NULL;
      }
      $value_digits = $value['digits'] . str_repeat('0', $value['exponent'] - $exponent);
      $offset_digits = $offset['digits'] . str_repeat('0', $offset['exponent'] - $exponent);
      $digits = $value['sign'] === $offset['sign']
        ? static::subtractDigits($value_digits, $offset_digits)
        : static::addDigits($value_digits, $offset_digits);
      $trimmed = rtrim($digits, '0');
      $exponent += strlen($digits) - strlen($trimmed);
      $digits = $trimmed;
    }

    if ($digits === '') {
      return TRUE;
    }

    // The step has no trailing zeros, so a difference with more significant
    // decimals than the step can never be a multiple of it.
    if ($exponent < $step['exponent']) {
      return FALSE;
    }

    // Calculate the remainder of the difference divided by the step's digits,
    // one digit at a time. The step's digits must fit in a native integer.
    $shift = $exponent - $step['exponent'];
    if ($shift > self::MAX_EXPONENT_RANGE || strlen($step['digits']) > 17) {
      return NULL;
    }
    $divisor = (int) $step['digits'];
    $remainder = 0;
    foreach (str_split($digits . str_repeat('0', $shift)) as $digit) {
      $remainder = ($remainder * 10 + (int) $digit) % $divisor;
    }
    return $remainder === 0;
  }

  /**
   * Converts a number to its sign, significant digits and decimal exponent.
   *
   * @param mixed $number
   *   The number to convert. If this is a string, it must be formatted as an
   *   integer or a float, and may use scientific notation.
   *
   * @return array|null
   *   NULL if the number cannot be converted, or an array with these keys:
   *   - sign: -1 for negative numbers, 0 for zero and 1 for positive numbers.
   *   - digits: The digits without leading or trailing zeros. An empty string
   *     for zero.
   *   - exponent: The power of 10 that the digits are multiplied by.
   *   - exact: FALSE if the number is a float that cannot be represented with
   *     PHP_FLOAT_DIG significant digits, TRUE otherwise.
   */
  private static function parseDecimal(mixed $number): ?array {
    $exact = TRUE;
    if (is_float($number)) {
      if (!is_finite($number)) {
        return NULL;
      }
      // Discard digits beyond the precision guaranteed by PHP, so that 0.1 is
      // not treated as 0.1000000000000000055511151231257827.
      $string = sprintf('%.' . (PHP_FLOAT_DIG - 1) . 'e', $number);
      $exact = (float) $string === $number;
    }
    elseif (is_int($number) || (is_string($number) && is_numeric($number))) {
      // Numeric strings may have the same surrounding whitespace that
      // is_numeric() allows.
      $string = trim((string) $number, " \t\n\r\v\f");
    }
    else {
      return NULL;
    }

    $sign = $string[0];
    if ($sign === '-' || $sign === '+') {
      $string = substr($string, 1);
    }

    // Handle scientific notation (for example, 1.0E-5).
    $exponent = 0;
    if (($e_position = stripos($string, 'e')) !== FALSE) {
      $exponent = (int) substr($string, $e_position + 1);
      $string = substr($string, 0, $e_position);
      // Exponents this large cannot be compared as exact decimals.
      if (abs($exponent) > 1000000000) {
        return NULL;
      }
    }

    $dot_position = strpos($string, '.');
    $fraction = $dot_position === FALSE ? '' : substr($string, $dot_position + 1);
    $integer = $dot_position === FALSE ? $string : substr($string, 0, $dot_position);

    $digits = ltrim($integer . $fraction, '0');
    $trimmed = rtrim($digits, '0');
    if ($trimmed === '') {
      return ['sign' => 0, 'digits' => '', 'exponent' => 0, 'exact' => $exact];
    }
    return [
      'sign' => $sign === '-' ? -1 : 1,
      'digits' => $trimmed,
      'exponent' => (int) $exponent - strlen($fraction) + strlen($digits) - strlen($trimmed),
      'exact' => $exact,
    ];
  }

  /**
   * Adds two non-negative integers that are given as strings of digits.
   *
   * @param string $a
   *   The first integer.
   * @param string $b
   *   The second integer.
   *
   * @return string
   *   The sum, which may have leading zeros.
   */
  private static function addDigits(string $a, string $b): string {
    $length = max(strlen($a), strlen($b));
    $a = str_pad($a, $length, '0', STR_PAD_LEFT);
    $b = str_pad($b, $length, '0', STR_PAD_LEFT);
    $result = '';
    $carry = 0;
    for ($i = $length - 1; $i >= 0; $i--) {
      $sum = (int) $a[$i] + (int) $b[$i] + $carry;
      $result = ($sum % 10) . $result;
      $carry = intdiv($sum, 10);
    }
    return $carry ? $carry . $result : $result;
  }

  /**
   * Subtracts two non-negative integers that are given as strings of digits.
   *
   * @param string $a
   *   The first integer.
   * @param string $b
   *   The second integer.
   *
   * @return string
   *   The absolute difference, which may have leading zeros.
   */
  private static function subtractDigits(string $a, string $b): string {
    $length = max(strlen($a), strlen($b));
    $a = str_pad($a, $length, '0', STR_PAD_LEFT);
    $b = str_pad($b, $length, '0', STR_PAD_LEFT);
    if (strcmp($a, $b) < 0) {
      [$a, $b] = [$b, $a];
    }
    $result = '';
    $borrow = 0;
    for ($i = $length - 1; $i >= 0; $i--) {
      $difference = (int) $a[$i] - (int) $b[$i] - $borrow;
      $borrow = $difference < 0 ? 1 : 0;
      $result = ($difference + 10 * $borrow) . $result;
    }
    return $result;
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
