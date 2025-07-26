<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Number;
use Drupal\TestTools\Extension\DeprecationBridge\ExpectDeprecationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

/**
 * Tests number manipulation utilities.
 *
 * @see \Drupal\Component\Utility\Number
 */
#[CoversClass(Number::class)]
#[Group('Utility')]
class NumberTest extends TestCase {

  use ExpectDeprecationTrait;

  /**
   * @covers \Drupal\Component\Utility\Number::normalize
   *
   * @param int|float|string $number
   *   The number to test the count on.
   * @param int $expected
   *   The expected number of significant decimals.
   *
   * @dataProvider providerNormalize
   */
  public function testNormalize($number, $expected) {
    $this->assertEquals($expected, Number::normalize($number));
  }

  /**
   * Provides data to self::testNormalize().
   */
  public static function providerNormalize() {
    return [
          ['', ''],
          [0, 0],
          [123456, 123456],
          [-123456, -123456],
          [0.0, 0.0],
          [0.12300000000000000001, 0.123],
          [1 / 3, 0.33333333333333],
          [10.00000000000000000999, 10],
          [1234.1234567800000000000000001, 1234.12345678],
          ['1234.1234567800000000000000001', '1234.1234567800000000000000001'],
    ];
  }

  /**
   * @covers \Drupal\Component\Utility\Number::countDecimals
   *
   * @param int $expected
   *   The expected number of significant decimals.
   * @param int|float|string $number
   *   The number to test the count on.
   *
   * @dataProvider providerCountDecimals
   */
  public function testCountDecimals($expected, $number) {
    $this->assertEquals($expected, Number::countDecimals($number));
  }

  /**
   * Provides data to self::testCountDecimals().
   */
  public static function providerCountDecimals() {
    return [
          [0, ''],
          [0, 0],
          [0, '0'],
          [0, 9],
          [0, '9'],
          [0, -9],
          [0, '-9'],
          [0, 999999999],
          [0, '999999999'],
          [0, -999999999],
          [0, '-999999999'],
          [0, 0.0],
          [1, '0.0'],
          [0, -0.0],
          [1, '-0.0'],
          // The maximum supported number of significant float decimals is 15.
          [0, 0.0000000000000],
          [0, -0.0000000000000],
          [9, -0.0000000090000],
          [9, -0.0000000090000],
          [9, -0.00000000900000],
          [9, -0.000000009000000000],
          [15, -0.0000000090000009],
          [15, -0.00000000900000099],
          [15, -0.000000009000000900009],
          [15, 0.000000009000000900009],
          // The floats are limited to the precision guaranteed by PHP.
          [14, 0.12345678901234567890],
          [12, 12.123456789012],
          [15, 123.12345678901234567890],
          [14, 1234.12345678901234567890],
          [14, 12345.12345678901234567890],
          [14, -12345.12345678901234567890],
          [14, 1 / 3],
          // Numeric strings do not suffer from the system-specific limitations
          // to float precision, so they can contain many more significant
          // decimals. This is especially useful when working with solutions
          // such as BCMath (https://secure.php.net/manual/en/book.bc.php)
          [15, '0.000000000000000'],
          [15, '-0.000000000000000'],
          [15, '-0.000000009000000'],
          [16, '-0.0000000090000000'],
          [20, '-0.00000000900000000000'],
          [20, '1234567890.12345678901234567890'],
          [20, '-1234567890.12345678901234567890'],
          [20, '1,234,567,890.12345678901234567890'],
    ];
  }

  /**
   * Tests Number::validStep() without offset.
   *
   * @param numeric $value
   *   The value argument for Number::validStep().
   * @param numeric $step
   *   The step argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   *
   * @legacy-covers ::validStep
   */
  #[DataProvider('providerTestValidStep')]
  public function testValidStep($value, $step, $expected): void {
    $return = Number::validStep($value, $step);
    $this->assertEquals($expected, $return);
  }

  /**
   * Tests Number::validStep() with offset.
   *
   * @param numeric $value
   *   The value argument for Number::validStep().
   * @param numeric $step
   *   The step argument for Number::validStep().
   * @param numeric $offset
   *   The offset argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   *
   * @legacy-covers ::validStep
   */
  #[DataProvider('providerTestValidStepOffset')]
  public function testValidStepOffset($value, $step, $offset, $expected): void {
    $return = Number::validStep($value, $step, $offset);
    $this->assertEquals($expected, $return);
  }

  /**
   * Provides data for self::testNumberStep().
   *
   * @see \Drupal\Tests\Component\Utility\Number::testValidStep
   */
  public static function providerTestValidStep() {
    return [
      // Value and step equal.
      [10.3, 10.3, TRUE],

      // Valid integer steps.
      [42, 21, TRUE],
      [42, 3, TRUE],

      // Valid float steps.
      [42, 10.5, TRUE],
      [1, 1 / 3, TRUE],
      [-100, 100 / 7, TRUE],
      [1000, -10, TRUE],

      // Valid and very small float steps.
      [1000.12345, 1e-10, TRUE],
      [3.9999999999999, 1e-13, TRUE],

      // Invalid integer steps.
      [100, 30, FALSE],
      [-10, 4, FALSE],

      // Invalid float steps.
      [6, 5 / 7, FALSE],
      [10.3, 10.25, FALSE],

      // Step mismatches very close to being valid.
      [70 + 9e-7, 10 + 9e-7, FALSE],
      [1936.5, 3e-8, FALSE],
    ];
  }

  /**
   * Data provider for testValidStepOffset().
   *
   * @see \Drupal\Tests\Component\Utility\NumberTest::testValidStepOffset()
   */
  public static function providerTestValidStepOffset() {
    return [
      // Try obvious fits.
      [11.3, 10.3, 1, TRUE],
      [100, 10, 50, TRUE],
      [-100, 90 / 7, -10, TRUE],
      [2 / 7 + 5 / 9, 1 / 7, 5 / 9, TRUE],

      // Ensure a small offset is still invalid.
      [10.3, 10.3, 0.0001, FALSE],
      [1 / 5, 1 / 7, 1 / 11, FALSE],

      // Try negative values and offsets.
      [1000, 10, -5, FALSE],
      [-10, 4, 0, FALSE],
      [-10, 4, -4, FALSE],
    ];
  }

  /**
   * Tests the alphadecimal conversion functions.
   *
   * @param int $value
   *   The integer value.
   * @param string $expected
   *   The expected alphadecimal value.
   *
   * @legacy-covers ::intToAlphadecimal
   * @legacy-covers ::alphadecimalToInt
   */
  #[DataProvider('providerTestConversions')]
  public function testConversions($value, $expected): void {
    $this->assertSame(Number::intToAlphadecimal($value), $expected);
    $this->assertSame($value, Number::alphadecimalToInt($expected));
  }

  /**
   * Data provider for testConversions().
   *
   * @return array
   *   An array containing:
   *     - The integer value.
   *     - The alphadecimal value.
   *
   * @see testConversions()
   */
  public static function providerTestConversions() {
    return [
      [0, '00'],
      [1, '01'],
      [10, '0a'],
      [20, '0k'],
      [35, '0z'],
      [36, '110'],
      [100, '12s'],
    ];
  }

  /**
   * Tests the alphadecimal conversion function input parameter checking.
   *
   * Number::alphadecimalToInt() must throw an exception
   * when non-alphanumeric characters are passed as input.
   *
   * @legacy-covers ::alphadecimalToInt
   */
  public function testAlphadecimalToIntThrowsExceptionWithMalformedStrings(): void {
    $this->expectException(\InvalidArgumentException::class);
    $nonAlphanumericChar = '#';
    Number::alphadecimalToInt($nonAlphanumericChar);
  }

  /**
   * Tests the alphadecimal conversion function keeps backward compatibility.
   *
   * Many tests and code rely on Number::alphadecimalToInt() returning 0
   * for degenerate values '' and NULL. We must ensure they are accepted.
   *
   * @legacy-covers ::alphadecimalToInt
   */
  #[IgnoreDeprecations]
  public function testAlphadecimalToIntReturnsZeroWithNullAndEmptyString(): void {
    $deprecationMessage = 'Passing NULL or an empty string to Drupal\Component\Utility\Number::alphadecimalToInt() is deprecated in drupal:11.2.0 and will be removed in drupal:12.0.0. See https://www.drupal.org/node/3494472';
    $this->expectDeprecation($deprecationMessage);
    $this->assertSame(0, Number::alphadecimalToInt(NULL));
    $this->expectDeprecation($deprecationMessage);
    $this->assertSame(0, Number::alphadecimalToInt(''));
  }

}
