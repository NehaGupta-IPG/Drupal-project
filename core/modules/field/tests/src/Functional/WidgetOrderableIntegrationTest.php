<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests orderable setting integration across all field widget types.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class WidgetOrderableIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_test',
    'entity_test',
    'user',
    'system',
    'datetime',
    'datetime_range',
    'file',
    'image',
    'options',
    'link',
    'text',
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

    // Create a test user with permission to manage entity_test content.
    $this->testUser = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
    ]);
    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests orderable setting across all supported widget types.
   */
  public function testOrderableSettingAcrossAllWidgetTypes(): void {
    // Keyed by widget type; some field types need extra storage settings.
    $widget_configs = [
      'email_default' => ['field_type' => 'email'],
      'uri' => ['field_type' => 'uri'],
      'link_default' => ['field_type' => 'link'],
      'datetime_default' => [
        'field_type' => 'datetime',
        'storage_settings' => ['datetime_type' => 'date'],
      ],
      'entity_reference_autocomplete' => [
        'field_type' => 'entity_reference',
        'storage_settings' => ['target_type' => 'entity_test'],
      ],
      'text_textfield' => ['field_type' => 'text'],
    ];

    $index = 0;
    foreach ($widget_configs as $widget_type => $config) {
      // Keep field names within the 32-character storage limit.
      $field_name = 'test_orderable_' . $index++;

      // Create field storage.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => $config['field_type'],
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
        'settings' => $config['storage_settings'] ?? [],
      ]);
      $field_storage->save();

      // Create field instance.
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'entity_test',
        'label' => "Test {$widget_type} Field",
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

      $form_display->setComponent($field_name, [
        'type' => $widget_type,
      ]);
      $form_display->save();

      $widget = $form_display->getRenderer($field_name);

      // The orderable setting exists and defaults to TRUE.
      $settings = $widget->getSettings();
      $this->assertArrayHasKey('orderable', $settings, "Orderable setting should exist for {$widget_type} widget.");
      $this->assertTrue($settings['orderable'], "Orderable setting should default to TRUE for {$widget_type} widget.");

      // Each widget calls parent::settingsForm() and parent::settingsSummary(),
      // so the checkbox and summary line appear for multi-value fields.
      $settings_form = $widget->settingsForm([], $this->createStub('\Drupal\Core\Form\FormStateInterface'));
      $this->assertArrayHasKey('orderable', $settings_form, "Orderable checkbox should appear for {$widget_type} widget.");
      $this->assertStringContainsString('Orderable:', implode(', ', $widget->settingsSummary()), "Orderable summary should appear for {$widget_type} widget.");
    }
  }

  /**
   * Tests template selection for different widget types.
   */
  public function testTemplateSelectionForAllWidgetTypes(): void {
    $widget_configs = [
      'email_default' => 'email',
      'uri' => 'uri',
    ];

    foreach ($widget_configs as $widget_type => $field_type) {
      $field_name = "test_template_{$widget_type}";

      // Create field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => $field_type,
        'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'entity_test',
        'label' => "Test Template {$widget_type}",
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

      $form_display->setComponent($field_name, [
        'type' => $widget_type,
        'settings' => ['orderable' => TRUE],
      ]);
      $form_display->save();

      $this->drupalGet('entity_test/add');

      // Should see draggable elements for orderable=TRUE.
      $this->assertSession()->fieldExists("{$field_name}[0][_weight]");

      // Test with orderable = FALSE.
      $form_display->setComponent($field_name, [
        'type' => $widget_type,
        'settings' => ['orderable' => FALSE],
      ]);
      $form_display->save();

      $this->drupalGet('entity_test/add');

      // Should NOT see weight fields for orderable=FALSE.
      $this->assertSession()->fieldNotExists("{$field_name}[0][_weight]");

      // But should still see the field input and add more button.
      $field_input_pattern = $this->getFieldInputPattern($field_type, $field_name);
      if ($field_input_pattern) {
        $this->assertSession()->fieldExists($field_input_pattern);
      }
      $this->assertSession()->buttonExists('Add another item');
    }
  }

  /**
   * Tests orderable setting with complex widget types.
   */
  public function testComplexWidgetTypes(): void {
    // Test link widget which we know is included in our configs.
    $link_storage = FieldStorageConfig::create([
      'field_name' => 'test_link',
      'entity_type' => 'entity_test',
      'type' => 'link',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $link_storage->save();

    $link_field = FieldConfig::create([
      'field_storage' => $link_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Link',
    ]);
    $link_field->save();

    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }

    $form_display->setComponent('test_link', [
      'type' => 'link_default',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Link widgets have URI and title fields.
    $this->assertSession()->fieldExists('test_link[0][uri]');
    $this->assertSession()->fieldExists('test_link[0][title]');
    $this->assertSession()->fieldNotExists('test_link[0][_weight]');
  }

  /**
   * Tests that single cardinality fields don't show orderable settings.
   */
  public function testSingleCardinalityWidgets(): void {
    $widget_types = [
      'email_default' => 'email',
      'uri' => 'uri',
    ];

    foreach ($widget_types as $widget_type => $field_type) {
      $field_name = "test_single_{$widget_type}";

      // Create single cardinality field.
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'entity_test',
        'type' => $field_type,
        'cardinality' => 1,
      ]);
      $field_storage->save();

      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'entity_test',
        'label' => "Test Single {$widget_type}",
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

      $form_display->setComponent($field_name, [
        'type' => $widget_type,
      ]);
      $form_display->save();

      // Test that widget settings form doesn't include orderable.
      $widget = $form_display->getRenderer($field_name);
      $settings_form = $widget->settingsForm([], $this->createStub('\Drupal\Core\Form\FormStateInterface'));

      $this->assertArrayNotHasKey('orderable', $settings_form, "Orderable setting should not appear for single cardinality {$widget_type} widget.");

      // Verify on form that there's no add more button or drag elements.
      $this->drupalGet('entity_test/add');
      $this->assertSession()->buttonNotExists('Add another item');
      $this->assertSession()->fieldNotExists("{$field_name}[0][_weight]");
    }
  }

  /**
   * Helper to get field input pattern for different field types.
   */
  private function getFieldInputPattern(string $field_type, string $field_name): ?string {
    $patterns = [
      'email' => "{$field_name}[0][value]",
      'uri' => "{$field_name}[0][value]",
      'boolean' => "{$field_name}[0][value]",
      'string' => "{$field_name}[0][value]",
    ];

    return $patterns[$field_type] ?? NULL;
  }

}
