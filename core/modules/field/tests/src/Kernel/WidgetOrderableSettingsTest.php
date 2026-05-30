<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the orderable setting functionality for field widgets.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class WidgetOrderableSettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_test',
    'entity_test',
    'options',
    'user',
    'system',
  ];

  /**
   * The entity form display.
   *
   * @var \Drupal\Core\Entity\Entity\EntityFormDisplay
   */
  protected $formDisplay;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field']);
  }

  /**
   * Tests that the orderable setting exists for field widgets.
   */
  public function testOrderableSettingExists(): void {
    // Create a multi-value text field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Field',
    ]);
    $field->save();

    // Create form display with string_textfield widget.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_field', [
      'type' => 'string_textfield',
      'settings' => [],
    ]);
    $this->formDisplay->save();

    // Get the widget and verify orderable setting exists.
    $widget = $this->formDisplay->getRenderer('test_field');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for multi-value field widgets.');
  }

  /**
   * Tests the default value of the orderable setting.
   */
  public function testOrderableDefaultValue(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_multi_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Multi Field',
    ]);
    $field->save();

    // Create form display with default settings.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_multi_field', [
      'type' => 'string_textfield',
    ]);
    $this->formDisplay->save();

    // Get the widget and verify orderable default value is TRUE.
    $widget = $this->formDisplay->getRenderer('test_multi_field');
    $orderable_setting = $widget->getSetting('orderable');

    $this->assertTrue($orderable_setting, 'Orderable setting should default to TRUE for backward compatibility.');
  }

  /**
   * Tests that orderable setting only appears for multi-value fields.
   */
  public function testOrderableSettingVisibility(): void {
    // Create a single-value field.
    $single_field_storage = FieldStorageConfig::create([
      'field_name' => 'test_single_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $single_field_storage->save();

    $single_field = FieldConfig::create([
      'field_storage' => $single_field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Single Field',
    ]);
    $single_field->save();

    // Create a multi-value field.
    $multi_field_storage = FieldStorageConfig::create([
      'field_name' => 'test_multi_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $multi_field_storage->save();

    $multi_field = FieldConfig::create([
      'field_storage' => $multi_field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Multi Field',
    ]);
    $multi_field->save();

    // Create form display.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_single_field', [
      'type' => 'string_textfield',
    ]);
    $this->formDisplay->setComponent('test_multi_field', [
      'type' => 'string_textfield',
    ]);
    $this->formDisplay->save();

    // Test single-value field widget settings form.
    $single_widget = $this->formDisplay->getRenderer('test_single_field');
    $single_settings_form = $single_widget->settingsForm([], $this->createStub('\Drupal\Core\Form\FormStateInterface'));

    $this->assertArrayNotHasKey('orderable', $single_settings_form, 'Orderable setting should not appear for single-value fields.');

    // Test multi-value field widget settings form.
    $multi_widget = $this->formDisplay->getRenderer('test_multi_field');
    $multi_settings_form = $multi_widget->settingsForm([], $this->createStub('\Drupal\Core\Form\FormStateInterface'));

    $this->assertArrayHasKey('orderable', $multi_settings_form, 'Orderable setting should appear for multi-value fields.');
    $this->assertEquals('checkbox', $multi_settings_form['orderable']['#type'], 'Orderable setting should be a checkbox.');
  }

  /**
   * Tests that orderable is hidden for widgets that handle multiple values.
   *
   * Widgets declaring multiple_values (checkboxes, select) render every value in
   * a single element and never use the tabledrag table, so the orderable setting
   * has no effect and must not appear in the settings form or summary.
   */
  public function testOrderableSettingHiddenForMultipleValuesWidgets(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_options_field',
      'entity_type' => 'entity_test',
      'type' => 'list_integer',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => ['allowed_values' => [1 => 'One', 2 => 'Two']],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Options Field',
    ]);
    $field->save();

    // The options_buttons (checkboxes) widget declares multiple_values: TRUE.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_options_field', [
      'type' => 'options_buttons',
    ]);
    $this->formDisplay->save();

    $widget = $this->formDisplay->getRenderer('test_options_field');
    $this->assertEquals('options_buttons', $widget->getPluginId(), 'Precondition: the widget is options_buttons.');

    $settings_form = $widget->settingsForm([], $this->createStub('\Drupal\Core\Form\FormStateInterface'));
    $this->assertArrayNotHasKey('orderable', $settings_form, 'Orderable must not appear for a widget that handles multiple values itself.');

    $this->assertStringNotContainsString('Orderable', implode(', ', $widget->settingsSummary()), 'Orderable must not appear in the summary for a multiple-values widget.');
  }

  /**
   * Tests the orderable setting summary display.
   */
  public function testOrderableSettingSummary(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_summary_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Summary Field',
    ]);
    $field->save();

    // Test with orderable = TRUE.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_summary_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $this->formDisplay->save();

    $widget_true = $this->formDisplay->getRenderer('test_summary_field');
    $summary_true = $widget_true->settingsSummary();

    $summary_string = implode(', ', $summary_true);
    $this->assertStringContainsString('Orderable:', $summary_string, 'Settings summary should contain "Orderable:" when orderable is TRUE.');
    $this->assertStringContainsString('Yes', $summary_string, 'Settings summary should show "Yes" when orderable is TRUE.');

    // Test with orderable = FALSE.
    $this->formDisplay->setComponent('test_summary_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $this->formDisplay->save();

    $widget_false = $this->formDisplay->getRenderer('test_summary_field');
    $summary_false = $widget_false->settingsSummary();

    $summary_string_false = implode(', ', $summary_false);
    $this->assertStringContainsString('Orderable:', $summary_string_false, 'Settings summary should contain "Orderable:" when orderable is FALSE.');
    $this->assertStringContainsString('No', $summary_string_false, 'Settings summary should show "No" when orderable is FALSE.');
  }

  /**
   * Tests that orderable setting persists correctly.
   */
  public function testOrderableSettingPersistence(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_persist_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Persist Field',
    ]);
    $field->save();

    // Create form display with orderable = FALSE.
    $this->formDisplay = EntityFormDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
    ]);
    $this->formDisplay->setComponent('test_persist_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $this->formDisplay->save();

    // Reload form display and verify setting persisted.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_persist_field');

    $this->assertFalse($component['settings']['orderable'], 'Orderable setting should persist as FALSE after save/reload.');

    // Test with orderable = TRUE.
    $this->formDisplay->setComponent('test_persist_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $this->formDisplay->save();

    // Reload and verify.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_persist_field');

    $this->assertTrue($component['settings']['orderable'], 'Orderable setting should persist as TRUE after save/reload.');
  }

  /**
   * Tests orderable setting across different widget types.
   */
  public function testOrderableSettingAcrossWidgetTypes(): void {
    $widget_types = [
      'string_textfield' => 'string',
      'number' => 'integer',
    ];

    foreach ($widget_types as $widget_type => $field_type) {
      // Create field storage and field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => "test_{$field_type}_field",
        'entity_type' => 'entity_test',
        'type' => $field_type,
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'entity_test',
        'label' => "Test {$field_type} Field",
      ]);
      $field->save();

      // Create form display using the default mode and unique field names.
      $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
      if (!$form_display) {
        $form_display = EntityFormDisplay::create([
          'targetEntityType' => 'entity_test',
          'bundle' => 'entity_test',
          'mode' => 'default',
        ]);
      }

      $form_display->setComponent("test_{$field_type}_field", [
        'type' => $widget_type,
      ]);
      $form_display->save();

      // Verify orderable setting exists.
      $widget = $form_display->getRenderer("test_{$field_type}_field");
      $settings = $widget->getSettings();

      $this->assertArrayHasKey('orderable', $settings, "Orderable setting should exist for {$widget_type} widget.");
      $this->assertTrue($settings['orderable'], "Orderable setting should default to TRUE for {$widget_type} widget.");
    }
  }

}
