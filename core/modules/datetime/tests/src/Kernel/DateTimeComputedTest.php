<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime\Kernel;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\datetime\DateTimeComputed;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests DateTimeComputed.
 */
#[CoversClass(DateTimeComputed::class)]
#[Group('datetime')]
#[RunTestsInSeparateProcesses]
class DateTimeComputedTest extends KernelTestBase {

  /**
   * Tests the ::getValue() method.
   */
  public function testGetValueWithNonStringValues(): void {
    // Test getValue() with non-string values.
    $non_string_values = [
      ['array'],
      (object) ['key' => 'value'],
      123,
      12.34,
      TRUE,
      NULL,
    ];

    foreach ($non_string_values as $test_value) {
      // Mock parent field item for test value.
      $parent = $this->createMock(FieldItemInterface::class);
      $parent->expects($this->atLeastOnce())
        ->method('__get')
        ->with('value')
        ->willReturn($test_value);

      // Mock data definition for date source.
      $definition = $this->createMock(DataDefinitionInterface::class);
      $definition->expects($this->atLeastOnce())
        ->method('getSetting')
        ->with('date source')
        ->willReturn('value');

      // Mock field definition for datetime type.
      $field_definition = $this->createMock(FieldDefinitionInterface::class);
      $field_definition->method('getSetting')
        ->with('datetime_type')
        ->willReturn(DateTimeItem::DATETIME_TYPE_DATETIME);

      // Associate the field definition with the parent field item.
      $parent->method('getFieldDefinition')
        ->willReturn($field_definition);

      // Create the computed field instance to test.
      $computed_field = new DateTimeComputed($definition, 'test', $parent);

      // Assert that non-string values result in NULL when getValue() is called.
      $this->assertNull(
        $computed_field->getValue(),
        'DateTimeComputed::getValue() should return NULL for non-string values'
      );
    }
  }

}
