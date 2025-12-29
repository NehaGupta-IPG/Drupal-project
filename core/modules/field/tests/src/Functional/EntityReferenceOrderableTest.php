<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests orderable setting functionality for entity reference widgets.
 *
 * @group field
 */
class EntityReferenceOrderableTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'user',
    'system',
    'node',
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

    // Create a node type for entity reference targets.
    NodeType::create([
      'type' => 'referenced_content',
      'name' => 'Referenced Content',
    ])->save();

    // Create test user.
    $this->testUser = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
      'create referenced_content content',
      'access content',
    ]);
    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests entity_reference_autocomplete widget orderable setting.
   */
  public function testEntityReferenceAutocompleteOrderableSetting(): void {
    // Create a multi-value entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_entity_ref',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Entity Reference',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_entity_ref', [
      'type' => 'entity_reference_autocomplete',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_entity_ref');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for entity_reference_autocomplete widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for entity_reference_autocomplete widget.');
  }

  /**
   * Tests entity_reference_autocomplete_tags widget orderable setting.
   */
  public function testEntityReferenceAutocompleteTagsOrderableSetting(): void {
    // Create a multi-value entity reference field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_entity_ref_tags',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Entity Reference Tags',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_entity_ref_tags', [
      'type' => 'entity_reference_autocomplete_tags',
    ]);
    $form_display->save();

    // Test that orderable setting exists and defaults to TRUE.
    $widget = $form_display->getRenderer('test_entity_ref_tags');
    $settings = $widget->getSettings();

    $this->assertArrayHasKey('orderable', $settings, 'Orderable setting should exist for entity_reference_autocomplete_tags widget.');
    $this->assertTrue($settings['orderable'], 'Orderable setting should default to TRUE for entity_reference_autocomplete_tags widget.');
  }

  /**
   * Tests entity reference widget settings summary.
   */
  public function testEntityReferenceWidgetSettingsSummary(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_ref_summary',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Reference Summary',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_ref_summary', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $widget_true = $form_display->getRenderer('test_ref_summary');
    $summary_true = $widget_true->settingsSummary();

    $summary_string = implode(', ', $summary_true);
    $this->assertStringContainsString('Orderable:', $summary_string, 'Settings summary should contain "Orderable:" when orderable is TRUE.');
    $this->assertStringContainsString('Yes', $summary_string, 'Settings summary should show "Yes" when orderable is TRUE.');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_ref_summary', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $widget_false = $form_display->getRenderer('test_ref_summary');
    $summary_false = $widget_false->settingsSummary();

    $summary_string_false = implode(', ', $summary_false);
    $this->assertStringContainsString('Orderable:', $summary_string_false, 'Settings summary should contain "Orderable:" when orderable is FALSE.');
    $this->assertStringContainsString('No', $summary_string_false, 'Settings summary should show "No" when orderable is FALSE.');
  }

  /**
   * Tests entity reference widget template selection based on orderable setting.
   */
  public function testEntityReferenceWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_ref_template',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Reference Template',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_ref_template', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should see entity reference field with drag elements.
    $this->assertSession()->fieldExists('test_ref_template[0][_weight]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_ref_template', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should NOT see weight fields when orderable is disabled.
    $this->assertSession()->fieldNotExists('test_ref_template[0][_weight]');

    // But should still see the autocomplete field and add more button.
    $this->assertSession()->fieldExists('test_ref_template[0][target_id]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests entity reference tags widget template selection.
   */
  public function testEntityReferenceTagsWidgetTemplateSelection(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_tags_template',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Tags Template',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_tags_template', [
      'type' => 'entity_reference_autocomplete_tags',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Tags widget should have the tags field but still orderable via table.
    $this->assertSession()->fieldExists('test_tags_template[target_id]');

    // Test with orderable = FALSE.
    $form_display->setComponent('test_tags_template', [
      'type' => 'entity_reference_autocomplete_tags',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Should still see the tags field.
    $this->assertSession()->fieldExists('test_tags_template[target_id]');
  }

  /**
   * Tests that single cardinality entity reference fields don't show orderable setting.
   */
  public function testSingleCardinalityEntityReferenceWidget(): void {
    // Create single cardinality field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_single_ref',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Single Reference',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_single_ref', [
      'type' => 'entity_reference_autocomplete',
    ]);
    $form_display->save();

    // Test that widget settings form doesn't include orderable.
    $widget = $form_display->getRenderer('test_single_ref');
    $settings_form = $widget->settingsForm([], $this->createMock('\Drupal\Core\Form\FormStateInterface'));

    $this->assertArrayNotHasKey('orderable', $settings_form, 'Orderable setting should not appear for single cardinality entity reference widget.');

    // Verify on form that there's no add more button or drag elements.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->buttonNotExists('Add another item');
    $this->assertSession()->fieldNotExists('test_single_ref[0][_weight]');
  }

  /**
   * Tests entity reference widget persistence of orderable setting.
   */
  public function testEntityReferenceOrderableSettingPersistence(): void {
    // Create field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_ref_persist',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Reference Persist',
      'settings' => [
        'handler' => 'default:node',
        'handler_settings' => [
          'target_bundles' => ['referenced_content'],
        ],
      ],
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

    $form_display->setComponent('test_ref_persist', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    // Reload form display and verify setting persisted.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_ref_persist');

    $this->assertFalse($component['settings']['orderable'], 'Orderable setting should persist as FALSE after save/reload.');

    // Test with orderable = TRUE.
    $form_display->setComponent('test_ref_persist', [
      'type' => 'entity_reference_autocomplete',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    // Reload and verify.
    $reloaded_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $component = $reloaded_display->getComponent('test_ref_persist');

    $this->assertTrue($component['settings']['orderable'], 'Orderable setting should persist as TRUE after save/reload.');
  }

}
