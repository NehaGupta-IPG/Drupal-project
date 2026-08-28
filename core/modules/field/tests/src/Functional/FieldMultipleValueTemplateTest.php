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
 * Tests the field multiple value template functionality.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class FieldMultipleValueTemplateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'field_test',
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

    // Create a test user with permission to manage entity_test content.
    $this->testUser = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
    ]);
    $this->drupalLogin($this->testUser);
  }

  /**
   * Tests that the correct template is used based on orderable setting.
   */
  public function testTemplateSelectionBasedOnOrderableSetting(): void {
    // Create a multi-value text field.
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

    // Test with orderable = TRUE (default behavior).
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent('test_multi_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => TRUE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // With orderable=TRUE, should see the draggable table with drag handles.
    $this->assertSession()->elementExists('xpath', '//table[contains(@class, "field-multiple-table")]');
    $this->assertSession()->elementExists('css', 'tr.draggable');
    $this->assertSession()->fieldExists('test_multi_field[0][_weight]');

    // Test with orderable = FALSE (new template).
    $form_display->setComponent('test_multi_field', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // With orderable=FALSE, should NOT see draggable table elements.
    $this->assertSession()->elementNotExists('xpath', '//table[contains(@class, "field-multiple-table")]');
    $this->assertSession()->elementNotExists('css', 'tr.draggable');
    $this->assertSession()->fieldNotExists('test_multi_field[0][_weight]');

    // But should still see the field and add more button.
    $this->assertSession()->fieldExists('test_multi_field[0][value]');
    $this->assertSession()->buttonExists('Add another item');
  }

  /**
   * Tests that add another item functionality works with the new template.
   */
  public function testAddAnotherItemFunctionality(): void {
    // Create unlimited multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_add_more',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Add More',
    ]);
    $field->save();

    // Configure with orderable = FALSE.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent('test_add_more', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Initially should have one field.
    $this->assertSession()->fieldExists('test_add_more[0][value]');
    $this->assertSession()->fieldNotExists('test_add_more[1][value]');

    // Click "Add another item".
    $this->submitForm([], 'Add another item');

    // Should now have two fields.
    $this->assertSession()->fieldExists('test_add_more[0][value]');
    $this->assertSession()->fieldExists('test_add_more[1][value]');
    $this->assertSession()->fieldNotExists('test_add_more[2][value]');

    // Add one more.
    $this->submitForm([], 'Add another item');

    // Should now have three fields.
    $this->assertSession()->fieldExists('test_add_more[0][value]');
    $this->assertSession()->fieldExists('test_add_more[1][value]');
    $this->assertSession()->fieldExists('test_add_more[2][value]');
  }

  /**
   * Tests data submission and storage with the new template.
   */
  public function testDataSubmissionAndStorage(): void {
    // Create multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_data_storage',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Data Storage',
    ]);
    $field->save();

    // Configure with orderable = FALSE.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent('test_data_storage', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Add multiple values.
    $this->submitForm([], 'Add another item');
    $this->submitForm([], 'Add another item');

    // Fill in values.
    $edit = [
      'test_data_storage[0][value]' => 'First value',
      'test_data_storage[1][value]' => 'Second value',
      'test_data_storage[2][value]' => 'Third value',
    ];
    $this->submitForm($edit, 'Save');

    // Verify entity was created and values saved.
    $this->assertSession()->pageTextContains('entity_test 1 has been created.');

    // Load the entity and verify field values.
    $entity = \Drupal::entityTypeManager()->getStorage('entity_test')->load(1);
    $field_values = $entity->get('test_data_storage')->getValue();

    $this->assertCount(3, $field_values, 'Three field values should be saved.');
    $this->assertEquals('First value', $field_values[0]['value'], 'First value should be saved correctly.');
    $this->assertEquals('Second value', $field_values[1]['value'], 'Second value should be saved correctly.');
    $this->assertEquals('Third value', $field_values[2]['value'], 'Third value should be saved correctly.');
  }

  /**
   * Tests template inheritance across different themes.
   */
  public function testTemplateInheritanceAcrossThemes(): void {
    // Create a multi-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_theme_inherit',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Theme Inheritance',
    ]);
    $field->save();

    // Configure with orderable = FALSE.
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent('test_theme_inherit', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    // Test with default theme (stark).
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists('test_theme_inherit[0][value]');
    $this->assertSession()->elementNotExists('css', 'tr.draggable');

    // Install and switch to Claro theme.
    $theme_installer = \Drupal::service('theme_installer');
    $theme_installer->install(['claro']);
    $config = \Drupal::configFactory()->getEditable('system.theme');
    $config->set('admin', 'claro')->save();

    // Clear caches to ensure theme change takes effect.
    drupal_flush_all_caches();

    // Test with Claro theme.
    $this->drupalGet('entity_test/add');
    $this->assertSession()->fieldExists('test_theme_inherit[0][value]');
    $this->assertSession()->elementNotExists('css', 'tr.draggable');

    // The new template should work with Claro's styling.
    $this->assertSession()->elementExists('css', '.js-form-item.form-item');
  }

  /**
   * Tests single cardinality fields use the elements variable correctly.
   */
  public function testSingleCardinalityFieldsUseElementsVariable(): void {
    // Create a single-value field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_single_field',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Single Field',
    ]);
    $field->save();

    // Configure form display (orderable setting doesn't affect single fields).
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'entity_test',
        'bundle' => 'entity_test',
        'mode' => 'default',
      ]);
    }
    $form_display->setComponent('test_single_field', [
      'type' => 'string_textfield',
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // Single field should appear normally.
    $this->assertSession()->fieldExists('test_single_field[0][value]');

    // Should not have add more button for single cardinality.
    $this->assertSession()->buttonNotExists('Add another item');

    // Should not have table or drag elements.
    $this->assertSession()->elementNotExists('css', 'table.field-multiple-table');
    $this->assertSession()->elementNotExists('css', 'tr.draggable');
  }

  /**
   * Tests that a field description is attached to the wrapper, not the items.
   *
   * On the non-orderable path the description's aria-describedby id must be
   * attached to the field wrapper. The rendered item list is iterated by the
   * template, so injecting the id there produces a stray empty item.
   */
  public function testDescriptionAttachedToWrapperNotItemList(): void {
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_described',
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'entity_test',
      'label' => 'Test Described',
      'description' => 'Help text for the described field.',
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
    $form_display->setComponent('test_described', [
      'type' => 'string_textfield',
      'settings' => ['orderable' => FALSE],
    ]);
    $form_display->save();

    $this->drupalGet('entity_test/add');

    // The page renders: a leaked id in the item list throws a render exception.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('test_described[0][value]');

    // The new entity form renders exactly one item for the single delta. The
    // description must not add a second, empty item.
    $wrapper = $this->assertSession()->elementExists('css', '.field--name-test-described');
    $items = $wrapper->findAll('css', '.field-multiple-item');
    $this->assertCount(1, $items, 'The description must not leak an extra item into the list.');

    // The description is associated with the field wrapper for screen readers.
    $description = $this->assertSession()->elementExists('css', '.field--name-test-described .description');
    $description_id = $description->getAttribute('id');
    $this->assertNotEmpty($description_id, 'The description element has an id.');
    $this->assertSession()->elementExists('css', '.field--name-test-described [aria-describedby="' . $description_id . '"]');
  }

}
