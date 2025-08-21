<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime_range\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime_range\DateTimeRangeDisplayOptions;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Daterange field.
 */
#[Group('datetime')]
#[RunTestsInSeparateProcesses]
class DateRangeFieldTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'entity_test', 'field_ui', 'datetime', 'datetime_range', 'field_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
      'administer content types',
      'administer node fields',
      'administer node display',
      'bypass node access',
      'administer entity_test fields',
    ]));
  }

  /**
   * Tests the conditional visibility of the 'Date separator' field.
   */
  public function testFromToSeparatorState(): void {
    $field_name = $this->randomMachineName();
    $this->drupalCreateContentType(['type' => 'date_content']);
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'daterange',
      'settings' => ['datetime_type' => DateRangeItem::DATETIME_TYPE_DATE],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'date_content',
    ]);
    $field->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'date_content')
      ->setComponent($field_name, [
        'type' => 'daterange_default',
        'label' => 'hidden',
        'settings' => [
          'format_type' => 'short',
          'separator' => 'THE_SEPARATOR',
        ],
      ])
      ->save();
    $this->drupalGet("admin/structure/types/manage/date_content/display/default");

    $page = $this->getSession()->getPage();
    $page->pressButton("{$field_name}_settings_edit");
    $this->assertSession()->waitForElement('css', '.ajax-new-content');

    $from_to_locator = 'fields[' . $field_name . '][settings_edit_form][settings][from_to]';
    $separator = $page->findField('Date separator');

    // Assert that date separator field is visible if 'from_to' is set to
    // DateTimeRangeDisplayOptions::Both.
    $this->assertSession()->fieldValueEquals($from_to_locator, DateTimeRangeDisplayOptions::Both->value);
    $this->assertTrue($separator->isVisible());
    // Assert that the date separator is not visible if 'from_to' is set to
    // DateTimeRangeDisplayOptions::StartDate or DateTimeRangeDisplayOptions::EndDate.
    $page->selectFieldOption($from_to_locator, DateTimeRangeDisplayOptions::StartDate->value);
    $this->assertFalse($separator->isVisible());
    $page->selectFieldOption($from_to_locator, DateTimeRangeDisplayOptions::EndDate->value);
    $this->assertFalse($separator->isVisible());
  }

  /**
   * Tests a required datetime field in a nested form and rebuilt via ajax.
   */
  public function testNestedFormDatetimeFieldAjax(): void {
    // Add the date range field to entity test.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_date_range',
      'entity_type' => 'entity_test',
      'type' => 'daterange',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'label' => 'Test DateRange',
      'bundle' => 'entity_test',
      'required' => TRUE,
    ]);
    $field->save();
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('field_test_date_range', ['type' => 'daterange_default'])
      ->save();
    // Add unlimited field to entity test which is used to trigger ajax on
    // the nested form.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_unlimited',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'label' => 'Unlimited field',
      'bundle' => 'entity_test',
    ])->save();
    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('field_unlimited', [])
      ->save();

    // Create two entities which we will be edited in the NestedFormTest form.
    $entity_type = 'entity_test';
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($entity_type);

    $entity_1 = $storage->create(['id' => 1]);
    $entity_1->enforceIsNew();
    $entity_1->save();

    $entity_2 = $storage->create(['id' => 2]);
    $entity_2->enforceIsNew();
    $entity_2->save();

    // Display the nested form (NestedFormTest).
    $this->drupalGet('test-entity/nested/1/2');

    // Assert the first date range field is empty.
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '');
    $add_more_button = $this->assertSession()->buttonExists('field_unlimited_add_more');

    // Trigger ajax nested form rebuild.
    $add_more_button->click();
    $field_1 = $this->assertSession()->waitForField('field_unlimited[1][value]');
    $this->assertNotEmpty($field_1, 'Successfully added another item.');
    // Assert date range still empty.
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '');

    // Submit the form (which should fail validation) & ensure values are
    // still empty.
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '');

    // Set partial date time to ensure values are maintained on ajax and
    // regular submit.
    $date_element = $this->assertSession()->fieldExists('field_test_date_range[0][value][date]');
    $date_element->setValue('12/31/2012');
    $add_more_button->click();
    $field_2 = $this->assertSession()->waitForField('field_unlimited[2][value]');
    $this->assertNotEmpty($field_2, 'Successfully added another item.');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '');
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '');

    $time_element = $this->assertSession()->fieldExists('field_test_date_range[0][value][time]');
    $time_element->setValue('03:00:00AM');
    $time_end_element = $this->assertSession()->fieldExists('field_test_date_range[0][end_value][time]');
    $time_end_element->setValue('04:00:00AM');
    $add_more_button->click();
    $field_3 = $this->assertSession()->waitForField('field_unlimited[3][value]');
    $this->assertNotEmpty($field_3, 'Successfully added another item.');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '03:00:00');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '04:00:00');
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][value][time]', '03:00:00');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_date_range[0][end_value][time]', '04:00:00');
  }

}
