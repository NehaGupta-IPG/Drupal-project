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
   * @param int|float|string $value
   *   The value argument for Number::validStep().
   * @param int|float|string $step
   *   The step argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   *
   * @covers ::validStep
   *
   * @dataProvider providerTestValidStep
   */
  #[DataProvider('providerTestValidStep')]
  public function testValidStep(int|float|string $value, int|float|string $step, bool $expected): void {
    $return = Number::validStep($value, $step);
    $this->assertEquals($expected, $return);
  }

  /**
   * Tests Number::validStep() with offset.
   *
   * @param int|float|string $value
   *   The value argument for Number::validStep().
   * @param int|float|string $step
   *   The step argument for Number::validStep().
   * @param float $offset
   *   The offset argument for Number::validStep().
   * @param bool $expected
   *   Expected return value from Number::validStep().
   *
   * @covers ::validStep
   *
   * @dataProvider providerTestValidStepOffset
   */
  #[DataProvider('providerTestValidStepOffset')]
  public function testValidStepOffset(int|float|string $value, int|float|string $step, float $offset, bool $expected): void {
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
      [1936.5, 3.0E-8, TRUE],
      [1000.12345, 1.0E-10, TRUE],
      [3.9999999999999, 1.0E-13, TRUE],

      // Invalid integer steps.
      [100, 30, FALSE],
      [-10, 4, FALSE],
      [1000, -10, FALSE],

      // Invalid float steps.
      [6, 5 / 7, FALSE],
      [10.3, 10.25, FALSE],
      [1000, -10.0, FALSE],

      // Step mismatches very close to being valid.
      [70 + 9.0E-7, 10 + 9.0E-7, FALSE],

      // Values that are strings that need to cast to numeric floats.
      ['0.00003', '0.00001', TRUE],
      ['0.00100001', '0.00100001', TRUE],
      ['0.00100001', '0.00000001', TRUE],

      // These floats are valid but might fail due to precision (rounding)
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

      // The precision of the value is higher than that of the step.
      [990088999.0099, 0.001, FALSE],
      [990088999.0099, 0.01, FALSE],

      // Ensure floats with more significant decimals that the guaranteed
      // minimum is normalized.
      [0.0000000000000900009, 0.0000000000000100001, TRUE],

      // Binary incompatibility edge cases.
      '0.3 step 0.1' => ['0.3', '0.1', TRUE],
      '0.7 step 0.35' => ['0.7', '0.35', TRUE],
      'Issue #2230909' => ['20.12345678', '0.00000001', TRUE],

      // Scientific notation.
      'Scientific step' => ['0.00001', '1.0E-5', TRUE],
      'Scientific value' => ['1.0E-5', '0.00001', TRUE],
      'Scientific mismatch' => ['1.0E-5', '3.0E-6', FALSE],

      // Trailing zeros.
      'Trailing zeros value' => ['1.00', '0.5', TRUE],
      'Trailing zeros step' => ['1.0', '0.50', TRUE],
      'Integer high precision' => ['10', '0.0001', TRUE],

      // Large numbers.
      'Large within limits' => ['99999999.9999999', '0.0000001', TRUE],
      'PHP_INT_MAX overflow' => ['9000000000000000000.99', '0.01', TRUE],

      // Original issue cases: precision=10, scale=4.
      '19999.0000 scale 4' => ['19999.0000', '0.0001', TRUE],
      '99999.0000 scale 4' => ['99999.0000', '0.0001', TRUE],

      // High precision for cryptocurrency (scale=8).
      'Bitcoin scale 8' => ['20.12345678', '0.00000001', TRUE],
      'Bitcoin mismatch' => ['20.123456789', '0.00000001', FALSE],

      // Zero edge cases.
      'Zero value' => ['0', '0.1', TRUE],
      'Zero with decimals' => ['0.00', '0.01', TRUE],

      // Very small numbers.
      'Small number valid' => ['0.0000001', '0.0000001', TRUE],
      'Small step large value' => ['1000', '0.000001', TRUE],

      // Step larger than value.
      'Step > value valid' => ['5', '10', FALSE],
      'Step > value at boundary' => ['10', '10', TRUE],

      // Repeating decimals.
      '1/7 multiples' => [3 / 7, 1 / 7, TRUE],
      '1/9 multiples' => [4 / 9, 1 / 9, TRUE],

      // Mixed string/numeric inputs.
      'String value float step' => ['1.5', 0.5, TRUE],
      'Float value string step' => [1.5, '0.5', TRUE],
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

      // Negative value with negative offset.
      'Negative offset valid' => ['-1.0', '0.5', -2.0, TRUE],
      'Negative decimal offset' => ['-1.2', '0.2', -2.0, TRUE],
      'Negative mismatch' => ['-1.2', '0.5', 0.0, FALSE],

      // Non-zero offset validation.
      'Offset valid' => ['3.1', '2.0', 1.1, TRUE],
      'Offset invalid' => ['3.2', '2.0', 1.1, FALSE],

      // Zero offset edge cases.
      'Zero value zero offset' => ['0', '0.1', 0.0, TRUE],
      'Value equals offset' => ['5.5', '0.5', 5.5, TRUE],

      // High precision offset.
      'High precision all' => ['0.123456789', '0.000000001', 0.123456789, TRUE],

      // Negative step (should fail).
      'Negative step' => ['10', -5, 0.0, FALSE],

      // Mixed signs with offset.
      'Pos value neg offset' => ['10', '5', -5.0, TRUE],
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
