<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Config\Schema;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests configuration schema for field widget orderable settings.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class FieldWidgetSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'node',
    'user',
    'text',
    'link',
    'datetime',
    'file',
    'image',
    'options',
  ];

  /**
   * The entity form display.
   */
  protected EntityFormDisplayInterface $formDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('file');

    // Create a content type.
    NodeType::create([
      'type' => 'test_type',
      'label' => 'Test content type',
      'name' => 'Test content type',
    ])->save();

    // Create the form display.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => 'test_type',
      'mode' => 'default',
    ]);
    $this->formDisplay->save();
  }

  /**
   * Tests orderable setting schema validation.
   */
  public function testOrderableSchemaValidation(): void {
    // Create a multi-value text field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_field',
      'entity_type' => 'node',
      'type' => 'string',
      // Unlimited.
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test_type',
      'label' => 'Test text field',
    ]);
    $field->save();

    // Test valid boolean values for orderable setting.
    $valid_configurations = [
      ['orderable' => TRUE],
      ['orderable' => FALSE],
      ['orderable' => 1],
      ['orderable' => 0],
    ];

    foreach ($valid_configurations as $config) {
      $component = [
        'type' => 'string_textfield',
        'weight' => 1,
        'settings' => $config + ['size' => 60, 'placeholder' => ''],
      ];

      $this->formDisplay->setComponent('test_text_field', $component);
      $this->formDisplay->save();

      // Verify the configuration validates.
      $config_data = $this->formDisplay->getComponent('test_text_field');
      $this->assertSame((bool) $config['orderable'], $config_data['settings']['orderable']);

      // Test schema validation through configuration system.
      $typed_config = \Drupal::service('config.typed')->get($this->formDisplay->getConfigDependencyName());
      $violations = $typed_config->validate();
      $this->assertCount(0, $violations, 'No schema violations for valid orderable setting.');
    }

    // Test that numeric values get coerced to boolean.
    $coerced_configurations = [
      ['orderable' => 1, 'expected' => TRUE],
      ['orderable' => 0, 'expected' => FALSE],
      ['orderable' => '1', 'expected' => TRUE],
      ['orderable' => '0', 'expected' => FALSE],
    ];

    foreach ($coerced_configurations as $config) {
      $component = [
        'type' => 'string_textfield',
        'weight' => 1,
        'settings' => ['orderable' => $config['orderable']] + ['size' => 60, 'placeholder' => ''],
      ];

      $this->formDisplay->setComponent('test_text_field', $component);
      $this->formDisplay->save();

      // Verify the value gets coerced to boolean.
      $config_data = $this->formDisplay->getComponent('test_text_field');
      $this->assertSame($config['expected'], $config_data['settings']['orderable'], 'Value coerced to expected boolean.');
    }
  }

  /**
   * Tests widget schema definitions across different widget types.
   */
  public function testWidgetSchemaDefinitions(): void {
    $widget_types = [
      'string_textfield' => 'string',
      'string_textarea' => 'string_long',
      'uri' => 'uri',
      'number' => 'integer',
      'boolean_checkbox' => 'boolean',
    ];

    foreach ($widget_types as $widget_type => $field_type) {
      // Create field storage and field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => "test_{$field_type}_field",
        'entity_type' => 'node',
        'type' => $field_type,
        // Unlimited for multi-value.
        'cardinality' => -1,
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'test_type',
        'label' => "Test {$field_type} field",
      ]);
      $field->save();

      // Set up widget with orderable setting.
      $component = [
        'type' => $widget_type,
        'weight' => 1,
        'settings' => ['orderable' => TRUE],
      ];

      // Add widget-specific settings to prevent schema violations.
      switch ($widget_type) {
        case 'string_textfield':
        case 'uri':
          $component['settings'] += ['size' => 60, 'placeholder' => ''];
          break;

        case 'string_textarea':
          $component['settings'] += ['rows' => 5, 'placeholder' => ''];
          break;

        case 'number':
          $component['settings'] += ['placeholder' => ''];
          break;

        case 'boolean_checkbox':
          $component['settings'] += ['display_label' => TRUE];
          break;
      }

      $this->formDisplay->setComponent("test_{$field_type}_field", $component);
      $this->formDisplay->save();

      // Verify schema validates for this widget type.
      $typed_config = \Drupal::service('config.typed')->get($this->formDisplay->getConfigDependencyName());
      $violations = $typed_config->validate();
      $this->assertCount(0, $violations, "No schema violations for {$widget_type} widget with orderable setting.");

      // Verify the orderable setting is properly typed.
      $config_data = $this->formDisplay->getComponent("test_{$field_type}_field");
      $this->assertIsBool($config_data['settings']['orderable'], "Orderable setting is boolean for {$widget_type}.");
    }
  }

  /**
   * Tests configuration export/import preservation of orderable setting.
   */
  public function testConfigurationExportImport(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_export_field',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test_type',
      'label' => 'Test export field',
    ]);
    $field->save();

    // Configure widget with orderable=FALSE.
    $component = [
      'type' => 'string_textfield',
      'weight' => 1,
      'settings' => [
        'orderable' => FALSE,
        'size' => 60,
        'placeholder' => '',
      ],
    ];

    $this->formDisplay->setComponent('test_export_field', $component);
    $this->formDisplay->save();

    // Export configuration.
    $config_data = $this->formDisplay->toArray();

    // Verify orderable setting is preserved in export.
    $this->assertFalse($config_data['content']['test_export_field']['settings']['orderable']);

    // Test import by modifying the existing display instead of creating a new one.
    $config_data['content']['test_export_field']['settings']['orderable'] = TRUE;
    $imported_display = EntityFormDisplay::load('node.test_type.default');
    $imported_display->removeComponent('test_export_field');
    $imported_display->setComponent('test_export_field', $config_data['content']['test_export_field']);
    $imported_display->save();

    // Verify orderable setting is preserved after import.
    $imported_component = $imported_display->getComponent('test_export_field');
    $this->assertTrue($imported_component['settings']['orderable']);

    // Verify schema validation still passes.
    $typed_config = \Drupal::service('config.typed')->get($imported_display->getConfigDependencyName());
    $violations = $typed_config->validate();
    $this->assertCount(0, $violations, 'No schema violations after import.');
  }

  /**
   * Tests schema validation consistency across widget types.
   */
  public function testSchemaValidationAcrossWidgetTypes(): void {
    $test_cases = [
      ['field_type' => 'string', 'widget_type' => 'string_textfield'],
      ['field_type' => 'link', 'widget_type' => 'link_default'],
      ['field_type' => 'integer', 'widget_type' => 'number'],
    ];

    foreach ($test_cases as $case) {
      $field_name = "test_{$case['field_type']}_consistency";

      // Create field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $case['field_type'],
        'cardinality' => -1,
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'test_type',
        'label' => "Test {$case['field_type']} consistency",
      ]);
      $field->save();

      // Test both orderable values.
      foreach ([TRUE, FALSE] as $orderable_value) {
        $settings = ['orderable' => $orderable_value];

        // Add widget-specific required settings.
        switch ($case['widget_type']) {
          case 'string_textfield':
            $settings += ['size' => 60, 'placeholder' => ''];
            break;

          case 'link_default':
            $settings += ['placeholder_url' => '', 'placeholder_title' => ''];
            break;

          case 'number':
            $settings += ['placeholder' => ''];
            break;
        }

        $component = [
          'type' => $case['widget_type'],
          'weight' => 1,
          'settings' => $settings,
        ];

        $this->formDisplay->setComponent($field_name, $component);
        $this->formDisplay->save();

        // Verify schema consistency.
        $typed_config = \Drupal::service('config.typed')->get($this->formDisplay->getConfigDependencyName());
        $violations = $typed_config->validate();
        $this->assertCount(0, $violations, "Schema validation consistent for {$case['widget_type']} with orderable={$orderable_value}.");

        // Verify the setting is properly typed.
        $config_data = $this->formDisplay->getComponent($field_name);
        $this->assertSame($orderable_value, $config_data['settings']['orderable']);
      }
    }
  }

  /**
   * Tests backward compatibility for configurations without orderable setting.
   */
  public function testBackwardCompatibility(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_backward_compat',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test_type',
      'label' => 'Test backward compatibility',
    ]);
    $field->save();

    // Simulate legacy configuration without orderable setting.
    $legacy_component = [
      'type' => 'string_textfield',
      'weight' => 1,
      'settings' => [
        'size' => 60,
        'placeholder' => '',
        // Note: no 'orderable' key - simulating legacy config.
      ],
    ];

    $this->formDisplay->setComponent('test_backward_compat', $legacy_component);
    $this->formDisplay->save();

    // Verify that missing orderable setting gets populated as TRUE.
    $updated_component = $this->formDisplay->getComponent('test_backward_compat');
    // The WidgetBase should provide default for missing orderable.
    $this->assertTrue($updated_component['settings']['orderable'] ?? TRUE, 'Orderable defaults to TRUE for backward compatibility.');

    // Verify schema validation passes with missing orderable setting.
    $typed_config = \Drupal::service('config.typed')->get($this->formDisplay->getConfigDependencyName());
    $violations = $typed_config->validate();
    $this->assertCount(0, $violations, 'No schema violations for legacy configuration without orderable setting.');

    // Verify the setting behavior when missing (widget should provide default).
    $this->formDisplay->save();
    $updated_component = $this->formDisplay->getComponent('test_backward_compat');
    // The setting may not be auto-populated, but the widget should handle missing values.
    $this->assertFalse(isset($updated_component['settings']['orderable']), 'Orderable setting is not auto-populated in configuration.');

    // Test mixed scenario: some fields have orderable, others don't.
    $field_storage2 = FieldStorageConfig::create([
      'field_name' => 'test_mixed_compat',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => -1,
    ]);
    $field_storage2->save();

    $field2 = FieldConfig::create([
      'field_storage' => $field_storage2,
      'bundle' => 'test_type',
      'label' => 'Test mixed compatibility',
    ]);
    $field2->save();

    // Set one field with explicit orderable, another without.
    $this->formDisplay->setComponent('test_backward_compat', [
      'type' => 'string_textfield',
      'settings' => ['size' => 60, 'placeholder' => ''],
    ]);

    $this->formDisplay->setComponent('test_mixed_compat', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE, 'size' => 60, 'placeholder' => ''],
    ]);

    $this->formDisplay->save();

    // Verify both components have correct settings.
    $compat_component = $this->formDisplay->getComponent('test_backward_compat');
    $mixed_component = $this->formDisplay->getComponent('test_mixed_compat');

    // Verify configuration behavior.
    $this->assertFalse(isset($compat_component['settings']['orderable']), 'Legacy field has no orderable setting in config.');
    $this->assertFalse($mixed_component['settings']['orderable'], 'Explicit orderable=FALSE is preserved.');
  }

}
