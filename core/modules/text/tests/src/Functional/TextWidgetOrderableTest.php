<?php

declare(strict_types=1);

namespace Drupal\Tests\text\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests orderable setting functionality for text widgets.
 *
 * @group text
 */
class TextWidgetOrderableTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'text',
    'entity_test',
    'user',
    'system',
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test user.
    $this->testUser = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
    ]);
    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests that text_textfield widget has orderable setting.
   */
  public function testTextTextfieldOrderableSetting(): void {
    // Create a multi-value text field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_textfield',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Textfield',
    ]);
    $field->save();

    // Create form display.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_textfield', [
      'type' => 'text_textfield',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_text_textfield');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for text_textfield widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for text_textfield widget.');
  }

  /**
   * Tests that text_textarea widget has orderable setting.
   */
  public function testTextTextareaOrderableSetting(): void {
    // Create a multi-value text_long field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_textarea',
      'entity_type' => 'entity_test',
      'type' => 'text_long',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Textarea',
    ]);
    $field->save();

    // Create form display.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_textarea', [
      'type' => 'text_textarea',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_text_textarea');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for text_textarea widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for text_textarea widget.');
  }

  /**
   * Tests that text_textarea_with_summary widget has orderable setting.
   */
  public function testTextTextareaWithSummaryOrderableSetting(): void {
    // Create a multi-value text_with_summary field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_with_summary',
      'entity_type' => 'entity_test',
      'type' => 'text_with_summary',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text With Summary',
    ]);
    $field->save();

    // Create form display.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_with_summary', [
      'type' => 'text_textarea_with_summary',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_text_with_summary');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for text_textarea_with_summary widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for text_textarea_with_summary widget.');
  }

  /**
   * Tests text widget settings summary.
   */
  public function testTextWidgetSettingsSummary(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_summary',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Summary',
    ]);
    $field->save();

    // Test with orderable = TRUE.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_summary', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $widget_true = $form_display->getRenderer('test_text_summary');
    $summary_true = $widget_true->settingsSummary();

    $summary_string = implode(', ', $summary_true);
    $this->assertStringContainsString('Orderable:', $summary_string, 'Settings summary should contain "Orderable:" when orderable is TRUE.');
    $this->assertStringContainsString('Yes', $summary_string, 'Settings summary should show "Yes" when orderable is TRUE.');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_text_summary', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $widget_false = $form_display->getRenderer('test_text_summary');
    $summary_false = $widget_false->settingsSummary();

    $summary_string_false = implode(', ', $summary_false);
    $this->assertStringContainsString('Orderable:', $summary_string_false, 'Settings summary should contain "Orderable:" when orderable is FALSE.');
    $this->assertStringContainsString('No', $summary_string_false, 'Settings summary should show "No" when orderable is FALSE.');
  }

  /**
   * Tests text widget template selection based on orderable setting.
   */
  public function testTextWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_template',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Template',
    ]);
    $field->save();

    // Test with orderable = TRUE (default).
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_template', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should see text field with drag elements.
    $this->assertSession()->fieldExists('test_text_template[0][_weight]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_text_template', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should NOT see weight fields when orderable is disabled.
    $this->assertSession()->fieldNotExists('test_text_template[0][_weight]');

    // But should still see the text field and add more button.
    $this->assertSession()->fieldExists('test_text_template[0][value]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests text textarea widget template selection.
   */
  public function testTextTextareaWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_textarea_template',
      'entity_type' => 'entity_test',
      'type' => 'text_long',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Textarea Template',
    ]);
    $field->save();

    // Test with orderable = TRUE (default).
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_textarea_template', [
      'type' => 'text_textarea',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should see textarea field with drag elements.
    $this->assertSession()->fieldExists('test_textarea_template[0][_weight]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_textarea_template', [
      'type' => 'text_textarea',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should NOT see weight fields when orderable is disabled.
    $this->assertSession()->fieldNotExists('test_textarea_template[0][_weight]');

    // But should still see the textarea field and add more button.
    $this->assertSession()->fieldExists('test_textarea_template[0][value]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests that single cardinality text fields don't show orderable setting.
   */
  public function testSingleCardinalityTextWidget(): void {
    // Create single cardinality field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_single_text',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Single Text',
    ]);
    $field->save();

    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_single_text', [
      'type' => 'text_textfield',
    ]);
    $form_display->save();

    // Test that widget settings form doesn't include orderable.
    $widget = $form_display->getRenderer('test_single_text');
    $settings_form = $widget->settingsForm([], $this->createMock('\Drupal\Core\Form\FormStateInterface'));

    $this->assertArrayNotHasKey('orderable', $settings_form, 'Orderable setting should not appear for single cardinality text widget.');

    // Verify on form that there's no add more button or drag elements.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->buttonNotExists('Add another item');
    $this->assertSession()->fieldNotExists('test_single_text[0][_weight]');
  }

  /**
   * Tests text widget persistence of orderable setting.
   */
  public function testTextOrderableSettingPersistence(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_persist',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Persist',
    ]);
    $field->save();

    // Create form display with orderable = FALSE.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_persist', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    // Reload form display and verify setting persisted.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_text_persist');

    $this->assertFalse($component['settings']['orderable'], 'Orderable setting should persist as FALSE after save/reload.');

    // Test with orderable = TRUE.
    $form_display->setComponent('test_text_persist', [
      'type' => 'text_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    // Reload and verify.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_text_persist');

    $this->assertTrue($component['settings']['orderable'], 'Orderable setting should persist as TRUE after save/reload.');
  }

  /**
   * Tests text widget backward compatibility with existing configurations.
   */
  public function testTextWidgetBackwardCompatibility(): void {
    // Create field without explicit orderable setting.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_text_compat',
      'entity_type' => 'entity_test',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Text Compatibility',
    ]);
    $field->save();

    // Create form display with minimal configuration.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_text_compat', [
      'type' => 'text_textfield',
      // No explicit orderable setting - should default to TRUE.
    ]);
    $form_display->save();

    // Verify that existing behavior (orderable = TRUE) is maintained.
    $widget = $form_display->getRenderer('test_text_compat');
    $orderable_setting = $widget->getSetting('orderable');

    $this->assertTrue($orderable_setting, 'Text widget should default to orderable=TRUE for backward compatibility.');

    // Verify drag elements are present by default.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists('test_text_compat[0][_weight]');
  }

}
