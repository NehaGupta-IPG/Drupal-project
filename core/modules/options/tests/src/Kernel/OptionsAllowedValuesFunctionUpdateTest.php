<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\options_test\OptionsAllowedValues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests field storage updates when using an allowed_values_function callback.
 *
 * Covers the case where a field with existing data is updated to use
 * allowed_values_function instead of a static allowed_values list.
 *
 * @see \Drupal\options\Hook\OptionsHooks::fieldStorageConfigUpdateForbid()
 */
#[Group('options')]
#[RunTestsInSeparateProcesses]
class OptionsAllowedValuesFunctionUpdateTest extends OptionsFieldUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options_test'];

  /**
   * Tests that switching to allowed_values_function respects in-use keys.
   *
   * @see \Drupal\options_test\OptionsAllowedValues::allowedValuesExcludingKey1()
   * @see \Drupal\options_test\OptionsAllowedValues::allowedValues()
   */
  public function testSwitchToAllowedValuesFunctionWithInUseKey(): void {
    // Store value 1 on an entity so the key is in use.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->value = 1;
    $entity->save();

    // Switching to a function that omits key 1 must be rejected.
    $this->fieldStorage->setSetting('allowed_values', []);
    $this->fieldStorage->setSetting('allowed_values_function', OptionsAllowedValues::class . '::allowedValuesExcludingKey1');

    try {
      $this->fieldStorage->save();
      $this->fail('Expected FieldStorageDefinitionUpdateForbiddenException was not thrown.');
    }
    catch (FieldStorageDefinitionUpdateForbiddenException $e) {
      $this->assertStringContainsString("A list field 'test_options' with existing data cannot have its keys changed. The keys that would be removed are '1'.", $e->getMessage());
    }

    // Switching to a function that retains key 1 must succeed.
    $this->fieldStorage->setSetting('allowed_values_function', OptionsAllowedValues::class . '::allowedValues');
    $this->fieldStorage->save();

    $saved = $this->fieldStorage->getSetting('allowed_values_function');
    $this->assertSame(OptionsAllowedValues::class . '::allowedValues', $saved);
  }

}
