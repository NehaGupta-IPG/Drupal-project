<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\options_test\OptionsAllowedValues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the 'Options' field types.
 */
#[Group('options')]
#[RunTestsInSeparateProcesses]
class OptionsFieldTest extends OptionsFieldUnitTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options', 'options_test'];

  /**
   * Tests that allowed values can be updated.
   */
  public function testUpdateAllowedValues(): void {
    // All three options appear.
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertArrayHasKey(1, $form[$this->fieldName]['widget'], 'Option 1 exists');
    $this->assertArrayHasKey(2, $form[$this->fieldName]['widget'], 'Option 2 exists');
    $this->assertArrayHasKey(3, $form[$this->fieldName]['widget'], 'Option 3 exists');

    // Use one of the values in an actual entity, and check that this value
    // cannot be removed from the list.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->value = 1;
    $entity->save();
    $this->fieldStorage->setSetting('allowed_values', [2 => 'Two']);
    try {
      $this->fieldStorage->save();
      $this->fail('Cannot update a list field storage to not include keys with existing data.');
    }
    catch (FieldStorageDefinitionUpdateForbiddenException) {
      // Expected exception; just continue testing.
    }
    // Empty the value, so that we can actually remove the option.
    unset($entity->{$this->fieldName});
    $entity->save();

    // Removed options do not appear.
    $this->fieldStorage->setSetting('allowed_values', [2 => 'Two']);
    $this->fieldStorage->save();
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertArrayNotHasKey(1, $form[$this->fieldName]['widget'], 'Option 1 does not exist');
    $this->assertArrayHasKey(2, $form[$this->fieldName]['widget'], 'Option 2 exists');
    $this->assertArrayNotHasKey(3, $form[$this->fieldName]['widget'], 'Option 3 does not exist');

    // Completely new options appear.
    $this->fieldStorage->setSetting('allowed_values', [10 => 'Update', 20 => 'Twenty']);
    $this->fieldStorage->save();
    // The entity holds an outdated field object with the old allowed values
    // setting, so we need to reinitialize the entity object.
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertArrayNotHasKey(1, $form[$this->fieldName]['widget'], 'Option 1 does not exist');
    $this->assertArrayNotHasKey(2, $form[$this->fieldName]['widget'], 'Option 2 does not exist');
    $this->assertArrayNotHasKey(3, $form[$this->fieldName]['widget'], 'Option 3 does not exist');
    $this->assertArrayHasKey(10, $form[$this->fieldName]['widget'], 'Option 10 exists');
    $this->assertArrayHasKey(20, $form[$this->fieldName]['widget'], 'Option 20 exists');

    // Options are reset when a new field with the same name is created.
    $this->fieldStorage->delete();
    FieldStorageConfig::create($this->fieldStorageDefinition)->save();
    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'required' => TRUE,
    ])->save();
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test', 'entity_test')
      ->setComponent($this->fieldName, [
        'type' => 'options_buttons',
      ])
      ->save();
    $entity = EntityTest::create();
    $form = \Drupal::service('entity.form_builder')->getForm($entity);
    $this->assertArrayHasKey(1, $form[$this->fieldName]['widget'], 'Option 1 exists');
    $this->assertArrayHasKey(2, $form[$this->fieldName]['widget'], 'Option 2 exists');
    $this->assertArrayHasKey(3, $form[$this->fieldName]['widget'], 'Option 3 exists');

    // Test the generateSampleValue() method.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->generateSampleItems();
    $this->entityValidateAndSave($entity);
  }

  /**
   * Tests that ::generateSampleItems does not fail with empty allowed values.
   */
  public function testGenerateSampleItemsWithNoAllowedValues(): void {
    $this->fieldStorage->setSetting('allowed_values', [])->save();
    $entity = EntityTest::create();
    $value = $entity->{$this->fieldName}->generateSampleItems();
    $this->assertNull($value);
  }

  /**
   * Tests that switching to allowed_values_function respects in-use keys.
   *
   * Covers the case where a field with existing data is updated to use
   * allowed_values_function instead of a static allowed_values list.
   *
   * @see \Drupal\options\Hook\OptionsHooks::fieldStorageConfigUpdateForbid()
   * @see \Drupal\options_test\OptionsAllowedValues::allowedValuesExcludingKey1()
   * @see \Drupal\options_test\OptionsAllowedValues::allowedValues()
   */
  public function testSwitchToAllowedValuesFunctionWithInUseKey(): void {
    // Store value 1 on an entity so the key is in use.
    $entity = EntityTest::create();
    $entity->{$this->fieldName}->value = 1;
    $entity->save();

    // Switching to a function that retains key 1 must succeed.
    $this->fieldStorage->setSetting('allowed_values_function', OptionsAllowedValues::class . '::allowedValues');
    $this->fieldStorage->save();

    $saved = $this->fieldStorage->getSetting('allowed_values_function');
    $this->assertSame(OptionsAllowedValues::class . '::allowedValues', $saved);

    // Switching to a function that omits key 1 must be rejected.
    $this->expectException(FieldStorageDefinitionUpdateForbiddenException::class);
    $this->expectExceptionMessage("A list field 'test_options' with existing data cannot have its keys changed. The keys that would be removed are '1'.");
    $this->fieldStorage->setSetting('allowed_values', []);
    $this->fieldStorage->setSetting('allowed_values_function', OptionsAllowedValues::class . '::allowedValuesExcludingKey1');
    $this->fieldStorage->save();
  }

}
