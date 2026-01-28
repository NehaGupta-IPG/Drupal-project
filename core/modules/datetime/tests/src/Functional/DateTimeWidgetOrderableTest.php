<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests orderable setting functionality for datetime widgets.
 */
#[Group('datetime')]
#[RunTestsInSeparateProcesses]
class DateTimeWidgetOrderableTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'datetime',
    'entity_test',
    'user',
    'system',
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
   * Tests that datetime_default widget has orderable setting.
   */
  public function testDateTimeDefaultOrderableSetting(): void {
    // Create a multi-value datetime field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datetime_field',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test DateTime Field',
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

    $form_display->setComponent('test_datetime_field', [
      'type' => 'datetime_default',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_datetime_field');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for datetime_default widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for datetime_default widget.');
  }

  /**
   * Tests that datetime_datelist widget has orderable setting.
   */
  public function testDateTimeDatelistOrderableSetting(): void {
    // Create a multi-value datetime field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datelist_field',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Datelist Field',
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

    $form_display->setComponent('test_datelist_field', [
      'type' => 'datetime_datelist',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_datelist_field');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for datetime_datelist widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for datetime_datelist widget.');
  }

  /**
   * Tests datetime widget settings summary.
   */
  public function testDateTimeWidgetSettingsSummary(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datetime_summary',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test DateTime Summary',
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

    $form_display->setComponent('test_datetime_summary', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $widget_true = $form_display->getRenderer('test_datetime_summary');
    $summary_true = $widget_true->settingsSummary();

    $summary_string = implode(', ', $summary_true);
    $this->assertStringContainsString('Orderable:', $summary_string, 'Settings summary should contain "Orderable:" when orderable is TRUE.');
    $this->assertStringContainsString('Yes', $summary_string, 'Settings summary should show "Yes" when orderable is TRUE.');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_datetime_summary', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $widget_false = $form_display->getRenderer('test_datetime_summary');
    $summary_false = $widget_false->settingsSummary();

    $summary_string_false = implode(', ', $summary_false);
    $this->assertStringContainsString('Orderable:', $summary_string_false, 'Settings summary should contain "Orderable:" when orderable is FALSE.');
    $this->assertStringContainsString('No', $summary_string_false, 'Settings summary should show "No" when orderable is FALSE.');
  }

  /**
   * Tests datetime widget template selection based on orderable setting.
   */
  public function testDateTimeWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datetime_template',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test DateTime Template',
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

    $form_display->setComponent('test_datetime_template', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should see datetime field with drag elements.
    $this->assertSession()->fieldExists('test_datetime_template[0][_weight]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_datetime_template', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should NOT see weight fields when orderable is disabled.
    $this->assertSession()->fieldNotExists('test_datetime_template[0][_weight]');

    // But should still see the datetime field and add more button.
    $this->assertSession()->fieldExists('test_datetime_template[0][value][date]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests datetime datelist widget template selection.
   */
  public function testDateTimeDatelistWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datelist_template',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Datelist Template',
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

    $form_display->setComponent('test_datelist_template', [
      'type' => 'datetime_datelist',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should see datetime datelist field with drag elements.
    $this->assertSession()->fieldExists('test_datelist_template[0][_weight]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_datelist_template', [
      'type' => 'datetime_datelist',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should NOT see weight fields when orderable is disabled.
    $this->assertSession()->fieldNotExists('test_datelist_template[0][_weight]');

    // But should still see the datelist field and add more button.
    $this->assertSession()->fieldExists('test_datelist_template[0][value][year]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests that single cardinality datetime fields don't show orderable setting.
   */
  public function testSingleCardinalityDateTimeWidget(): void {
    // Create single cardinality field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_single_datetime',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Single DateTime',
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

    $form_display->setComponent('test_single_datetime', [
      'type' => 'datetime_default',
    ]);
    $form_display->save();

    // Test that widget settings form doesn't include orderable.
    $widget = $form_display->getRenderer('test_single_datetime');
    $settings_form = $widget->settingsForm([], $this->createMock('\Drupal\Core\Form\FormStateInterface'));

    $this->assertArrayNotHasKey('orderable', $settings_form, 'Orderable setting should not appear for single cardinality datetime widget.');

    // Verify on form that there's no add more button or drag elements.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->buttonNotExists('Add another item');
    $this->assertSession()->fieldNotExists('test_single_datetime[0][_weight]');
  }

  /**
   * Tests datetime widget persistence of orderable setting.
   */
  public function testDateTimeOrderableSettingPersistence(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datetime_persist',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test DateTime Persist',
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

    $form_display->setComponent('test_datetime_persist', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    // Reload form display and verify setting persisted.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_datetime_persist');

    $this->assertFalse($component['settings']['orderable'], 'Orderable setting should persist as FALSE after save/reload.');

    // Test with orderable = TRUE.
    $form_display->setComponent('test_datetime_persist', [
      'type' => 'datetime_default',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    // Reload and verify.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_datetime_persist');

    $this->assertTrue($component['settings']['orderable'], 'Orderable setting should persist as TRUE after save/reload.');
  }

  /**
   * Tests datetime widget backward compatibility with existing configurations.
   */
  public function testDateTimeWidgetBackwardCompatibility(): void {
    // Create field without explicit orderable setting.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_datetime_compat',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test DateTime Compatibility',
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

    $form_display->setComponent('test_datetime_compat', [
      'type' => 'datetime_default',
      // No explicit orderable setting - should default to TRUE.
    ]);
    $form_display->save();

    // Verify that existing behavior (orderable = TRUE) is maintained.
    $widget = $form_display->getRenderer('test_datetime_compat');
    $orderable_setting = $widget->getSetting('orderable');

    $this->assertTrue($orderable_setting, 'DateTime widget should default to orderable=TRUE for backward compatibility.');

    // Verify drag elements are present by default.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists('test_datetime_compat[0][_weight]');
  }

}
