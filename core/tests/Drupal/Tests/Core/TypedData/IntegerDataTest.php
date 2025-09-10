<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\UnsupportedDataTypeConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\TypedData\Plugin\DataType\IntegerData.
 */
#[CoversClass(IntegerData::class)]
#[Group('TypedData')]
class IntegerDataTest extends UnitTestCase {

  /**
   * Ensures that getCastValue() returns an exception when a lower than PHP_INT_MIN value is given.
   */
  public function testOutOfRangeValues(): void {
    $integer = new IntegerData(DataDefinition::create('integer'));
    $lower = ((string) constant('PHP_INT_MIN')) . '999';
    $higher = ((string) constant('PHP_INT_MAX')) . '999';

    $integer->setValue($lower);
    $this->expectException(UnsupportedDataTypeConfigException::class);
    $integer->getCastedValue();

    $integer->setValue($higher);
    $this->expectException(UnsupportedDataTypeConfigException::class);
    $integer->getCastedValue();
  }

  /**
   * Ensures that getCastValue() returns the correct integer when a valid value is given.
   */
  public function testValidValues(): void {
    $integer = new IntegerData(DataDefinition::create('integer'));
    $valid = ((string) constant('PHP_INT_MAX'));
    $validNegative = ((string) constant('PHP_INT_MIN'));

    $integer->setValue($valid);
    $this->assertSame(constant('PHP_INT_MAX'), $integer->getCastedValue());

    $integer->setValue($validNegative);
    $this->assertSame(constant('PHP_INT_MIN'), $integer->getCastedValue());
  }

}
