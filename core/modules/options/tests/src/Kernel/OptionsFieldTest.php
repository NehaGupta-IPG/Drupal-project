<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel;

use Drupal\Core\Entity\Exception\FieldStorageDefinitionUpdateForbiddenException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\DataProvider;
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
  protected static $modules = ['options'];

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
   * Tests the 'option_label' property for 'Options' field types.
   */
  #[DataProvider('providerOptions')]
  public function testOptionLabelProperty(string $type, array $allowed_values): void {
    // Remove field storage set by OptionsFieldUnitTestBase() to allow for
    // field storage to be dynamically created with data
    // from providerOptions().
    $this->fieldStorage->delete();

    $this->fieldStorageDefinition = [
      'field_name' => $this->fieldName,
      'entity_type' => 'entity_test',
      'type' => $type,
      'cardinality' => 1,
      'settings' => [
        'allowed_values' => $allowed_values,
      ],
    ];
    $this->fieldStorage = FieldStorageConfig::create($this->fieldStorageDefinition);
    $this->fieldStorage->save();

    $field = FieldConfig::create([
      'field_storage' => $this->fieldStorage,
      'bundle' => 'entity_test',
    ]);
    $field->save();

    $first_option_key = array_key_first($allowed_values);

    $entity = EntityTest::create();
    $entity->{$this->fieldName}->value = $first_option_key;
    $entity->save();

    $this->assertSame($entity->{$this->fieldName}->option_label, $allowed_values[$first_option_key]);
  }

  /**
   * The dataProvider for testOptionLabelProperty().
   */
  public static function providerOptions(): array {
    return [
      'list_float' => [
        // Field type.
        'list_float',
        // Allowed values.
        [
          1 => 'One',
          2 => 'Two',
          3 => 'Three',
        ],
      ],
      'list_integer' => [
        // Field type.
        'list_integer',
        // Allowed values.
        [
          1 => 'One',
          2 => 'Two',
          3 => 'Three',
        ],
      ],
      'list_string' => [
        // Field type.
        'list_string',
        // Allowed values.
        [
          'key1' => 'One',
          'key2' => 'Two',
          'key3' => 'Three',
        ],
      ],
    ];
  }

}
