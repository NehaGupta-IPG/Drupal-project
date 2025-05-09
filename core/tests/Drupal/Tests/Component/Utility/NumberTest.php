<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\Number;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests number manipulation utilities.
 *
 * @see \Drupal\Component\Utility\Number
 */
#[CoversClass(Number::class)]
#[Group('Utility')]
class NumberTest extends TestCase {

  /**
   * Tests Number::validStep() without offset.
   *
   * @dataProvider providerTestValidStep
   * @covers ::validStep
   *
   * @param int|float|string $value
   *   The value argument for Number::validStep().
   * @param int|float|string $step
   *   The step argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   */
  #[DataProvider('providerTestValidStep')]
  public function testValidStep(float|int $value, float|int $step, bool $expected): void {
    $return = Number::validStep($value, $step);
    $this->assertEquals($expected, $return);
  }

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
  public function testNormalize($number, $expected): void {
    $this->assertEquals($expected, Number::normalize($number));
  }

  /**
   * Provides data to self::testNormalize().
   */
  public static function providerNormalize(): array {
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
   * @covers ::countSignificantDecimals
   *
   * @dataProvider provideCountSignificantDecimals
   *
   * @param int $expected
   *   The expected number of significant decimals.
   * @param int|float|string $number
   *   The number to test the count on.
   */
  public function testCountSignificantDecimals($expected, $number): void {
    $this->assertEquals($expected, Number::countSignificantDecimals($number));
  }

  /**
   * Provides data to self::testCountSignificantDecimals().
   */
  public static function provideCountSignificantDecimals(): array {
    return [
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
      [0, 'Hello'],
      [0, '0'],
      [0, FALSE],
      [1, '0.0'],
      [0, -0.0],
      [1, '-0.0'],
      // The maximum supported number of significant float decimals is 14.
      [0, 0.0000000000000],
      [0, -0.0000000000000],
      [9, -0.0000000090000],
      [9, -0.0000000090000],
      [9, -0.00000000900000],
      [9, -0.000000009000000000],
      [14, -0.00000000900009],
      [14, -0.0000000090000099],
      [14, -0.0000000090000090009],
      // Numeric strings do not suffer from the system-specific limitations to
      // float precision, so they can contain many more significant decimals.
      // This is especially useful when working with solutions such as BCMath
      // (https://secure.php.net/manual/en/book.bc.php)
      [14, '0.00000000000000'],
      [14, '-0.00000000000000'],
      [14, '-0.00000000900000'],
      [16, '-0.0000000090000000'],
      [20, '-0.00000000900000000000'],
    ];
  }

  /**
   * Tests Number::validStep() with offset.
   *
   * @dataProvider providerTestValidStepOffset
   * @covers ::validStep
   *
   * @param int|float|string $value
   *   The value argument for Number::validStep().
   * @param int|float|string $step
   *   The step argument for Number::validStep().
   * @param float $offset
   *   The offset argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   */
  #[DataProvider('providerTestValidStepOffset')]
  public function testValidStepOffset(float|int $value, float|int $step, int|float $offset, bool $expected): void {
    $return = Number::validStep($value, $step, $offset);
    $this->assertEquals($expected, $return);
  }

  /**
   * Provides data for self::testNumberStep().
   *
   * @see \Drupal\Tests\Component\Utility\Number::testValidStep
   */
  public static function providerTestValidStep(): array {
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

      // Valid and very small float steps.
      [1936.5, 3e-8, TRUE],
      [1000.12345, 1e-10, TRUE],
      [3.9999999999999, 1e-13, TRUE],

      // Invalid integer steps.
      [100, 30, FALSE],
      [-10, 4, FALSE],
      [1000, -10, FALSE],

      // Invalid float steps.
      [6, 5 / 7, FALSE],
      [10.3, 10.25, FALSE],
      [1000, -10.0, FALSE],

      // Step mismatches very close to being valid.
      [70 + 9e-7, 10 + 9e-7, FALSE],

      // These floats are valid, but might fail due to precision (rounding)
      // errors that are inherent to floating point numbers.
      [3333333, 0.01, TRUE],
      [4444444, 0.01, TRUE],
      [9990009888.96, 0.01, TRUE],
      [9990009888.99, 0.01, TRUE],
      [990088999.0096, 0.0001, TRUE],
      [990088999.0099, 0.0001, TRUE],
      [4031239412.52, 0.01, TRUE],
      [90000000000.00, 0.01, TRUE],
      [9999999999.99, 0.01, TRUE],
      [-3.1933172, 0.0000001, TRUE],
      [1109.87, 0.01, TRUE],
      [70000000, 0.01, TRUE],
      [70000000.00, 0.01, TRUE],
      [13517282.20, 0.01, TRUE],
      [13517282.21, 0.01, TRUE],

      // Precision of the value is higher than that of the step.
      [990088999.0099, 0.001, FALSE],
      [990088999.0099, 0.01, FALSE],

      // Ensure floats with more significant decimals that the guaranteed
      // minimum are normalized.
      [0.0000000000000900009, 0.0000000000000100001, TRUE],
    ];
  }

  /**
   * Data provider for testValidStepOffset().
   *
   * @see \Drupal\Tests\Component\Utility\NumberTest::testValidStepOffset()
   */
  public static function providerTestValidStepOffset(): array {
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
  public function testConversions(int $value, string $expected): void {
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
  public static function providerTestConversions(): array {
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
   */
  public function testAlphadecimalToIntThrowsExceptionWithMalformedStrings(): void {
    $this->expectException(\InvalidArgumentException::class);
    $nonAlphanumericChar = '#';
    Number::alphadecimalToInt($nonAlphanumericChar);
  }

}
