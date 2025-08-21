<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests for the DateTime field.
 *
 * @group datetime
 */
class DateTimeFieldTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_test', 'entity_test', 'datetime'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add datetime field to entity_test.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test_datetime',
      'entity_type' => 'entity_test',
      'type' => 'datetime',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'label' => 'Test DateTime',
      'bundle' => 'entity_test',
      'required' => TRUE,
    ])->save();

    EntityFormDisplay::load('entity_test.entity_test.default')
      ->setComponent('field_test_datetime', ['type' => 'datetime_default'])
      ->save();

    $web_user = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]);
    $this->drupalLogin($web_user);
  }

  /**
   * Tests a required datetime field in a nested form and rebuilt via ajax.
   */
  public function testNestedFormDatetimeFieldAjax(): void {
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

    // Assert the first date time field is empty.
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '');
    $add_more_button = $this->assertSession()->buttonExists('field_unlimited_add_more');

    // Trigger ajax nested form rebuild.
    $add_more_button->click();
    $field_1 = $this->assertSession()->waitForField('field_unlimited[1][value]');
    $this->assertNotEmpty($field_1, 'Successfully added another item.');
    // Assert date time still empty.
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '');

    // Submit the form (which should fail validation) & ensure values are
    // still empty.
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '');

    // Set partial date time to ensure values are maintained on ajax and
    // regular submit.
    $date_element = $this->assertSession()->fieldExists('field_test_datetime[0][value][date]');
    $date_element->setValue('12/31/2012');
    $add_more_button->click();
    $field_2 = $this->assertSession()->waitForField('field_unlimited[2][value]');
    $this->assertNotEmpty($field_2, 'Successfully added another item.');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '');
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '2012-12-31');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '');

    $date_element = $this->assertSession()->fieldExists('field_test_datetime[0][value][date]');
    $date_element->setValue('');
    $time_element = $this->assertSession()->fieldExists('field_test_datetime[0][value][time]');
    $time_element->setValue('03:00:00AM');
    $add_more_button->click();
    $field_3 = $this->assertSession()->waitForField('field_unlimited[3][value]');
    $this->assertNotEmpty($field_3, 'Successfully added another item.');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '03:00:00');
    $this->submitForm([], 'Save');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][date]', '');
    $this->assertSession()->fieldValueEquals('field_test_datetime[0][value][time]', '03:00:00');
  }

}
