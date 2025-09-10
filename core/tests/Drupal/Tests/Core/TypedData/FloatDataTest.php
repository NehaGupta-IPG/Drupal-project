<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\TypedData;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Plugin\DataType\FloatData;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Config\UnsupportedDataTypeConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\TypedData\Plugin\DataType\FloatData.
 */
#[CoversClass(FloatData::class)]
#[Group('TypedData')]
class FloatDataTest extends UnitTestCase {

  /**
   * Ensures that getCastValue() returns an exception when a lower than PHP_INT_MIN value is given.
   */
  public function testOutOfRangeValues(): void {
    $float = new FloatData(DataDefinition::create('float'));
    $lower = ((string) constant('PHP_FLOAT_MIN')) . '999';
    $higher = ((string) constant('PHP_FLOAT_MAX')) . '999';

    $float->setValue($lower);
    $this->expectException(UnsupportedDataTypeConfigException::class);
    $float->getCastedValue();

    $float->setValue($higher);
    $this->expectException(UnsupportedDataTypeConfigException::class);
    $float->getCastedValue();
  }

  /**
   * Ensures that getCastValue() returns the correct integer when a valid value is given.
   */
  public function testValidValues(): void {
    $float = new FloatData(DataDefinition::create('float'));
    $float->setValue("0.5");
    $this->assertSame(0.5, $float->getCastedValue());
  }

}

