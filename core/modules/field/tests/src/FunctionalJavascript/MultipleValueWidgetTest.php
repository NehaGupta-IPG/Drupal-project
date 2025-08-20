<?php

declare(strict_types=1);

namespace Drupal\Tests\field\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests widget form for a multiple value field.
 */
#[Group('field')]
class MultipleValueWidgetTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_test',
    'entity_test',
    'node',
    'views',
    'entity_reference_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]);
    $this->drupalLogin($account);

    $field = [
      'field_name' => 'field_unlimited',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => $this->randomMachineName() . '_label',
      'description' => '[site:name]_description',
      'weight' => mt_rand(0, 127),
      'settings' => [
        'test_field_setting' => $this->randomMachineName(),
      ],
    ];

    FieldStorageConfig::create([
      'field_name' => 'field_unlimited',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create($field)->save();

    $entity_form_display = EntityFormDisplay::load($field['entity_type'] . '.' . $field['bundle'] . '.default');
    $entity_form_display->setComponent($field['field_name'])->save();
  }

  /**
   * Tests the 'Add more' functionality.
   */
  public function testFieldMultipleValueWidget(): void {
    $this->drupalGet('entity_test/add');

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $add_more_button = $page->findButton('field_unlimited_add_more');

    // First set a value on the first input field.
    $field_0 = $page->findField('field_unlimited[0][value]');
    $field_0->setValue('1');

    $field_0_remove_button = $page->findButton('field_unlimited_0_remove_button');
    $this->assertNotEmpty($field_0_remove_button, 'First field has a remove button.');

    // Add another item.
    $add_more_button->click();
    $field_1 = $assert_session->waitForField('field_unlimited[1][value]');
    $this->assertNotEmpty($field_1, 'Successfully added another item.');

    $field_1_remove_button = $page->findButton('field_unlimited_1_remove_button');
    $this->assertNotEmpty($field_1_remove_button, 'Also second field has a remove button.');

    // Validate the value of the first field has not changed.
    $this->assertEquals('1', $field_0->getValue(), 'Value for the first item has not changed.');

    // Validate the value of the second item is empty.
    $this->assertEmpty($field_1->getValue(), 'Value for the second item is currently empty.');

    // Add another item.
    $add_more_button->click();
    $field_2 = $assert_session->waitForField('field_unlimited[2][value]');
    $this->assertNotEmpty($field_2, 'Successfully added another item.');

    // Set values for the 2nd and 3rd fields to validate dragging.
    $field_1->setValue('2');
    $field_2->setValue('3');

    $field_weight_0 = $page->findField('field_unlimited[0][_weight]');
    $field_weight_1 = $page->findField('field_unlimited[1][_weight]');
    $field_weight_2 = $page->findField('field_unlimited[2][_weight]');

    // Assert starting situation matches expectations.
    $this->assertGreaterThan($field_weight_0->getValue(), $field_weight_1->getValue());
    $this->assertGreaterThan($field_weight_1->getValue(), $field_weight_2->getValue());

    // Drag the first row after the third row.
    $dragged = $field_0->find('xpath', 'ancestor::tr[contains(@class, "draggable")]//a[starts-with(@class, "tabledrag-handle")]');
    $target = $field_2->find('xpath', 'ancestor::tr[contains(@class, "draggable")]');
    $dragged->dragTo($target);

    // Assert the order of items is updated correctly after dragging.
    $this->assertGreaterThan($field_weight_2->getValue(), $field_weight_0->getValue());
    $this->assertGreaterThan($field_weight_1->getValue(), $field_weight_2->getValue());

    // Validate the order of items conforms to the last drag action after a
    // updating the form via the server.
    $add_more_button->click();
    $field_3 = $assert_session->waitForField('field_unlimited[3][value]');
    $this->assertNotEmpty($field_3);
    $this->assertGreaterThan($field_weight_2->getValue(), $field_weight_0->getValue());
    $this->assertGreaterThan($field_weight_1->getValue(), $field_weight_2->getValue());

    // Validate no extraneous widget is displayed.
    $element = $page->findField('field_unlimited[4][value]');
    $this->assertEmpty($element);

    // Test removing items/values.
    $field_0_remove_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Test the updated widget.
    // First item is the initial second item.
    $this->assertEquals('2', $field_0->getValue(), 'Value for the first item has changed.');
    // We do not have the initial first item anymore.
    $this->assertEmpty($field_2->getValue(), 'Value for the third item is currently empty.');
    $element = $page->findField('field_unlimited[3][value]');
    $this->assertEmpty($element);

    // We can also remove empty items.
    $field_2_remove_button = $page->findButton('field_unlimited_2_remove_button');
    $field_2_remove_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $element = $page->findField('field_unlimited[2][value]');
    $this->assertEmpty($element, 'Empty field also removed.');

    // Assert that the wrapper exists and isn't nested.
    $this->assertSession()->elementsCount('css', '[data-drupal-selector="edit-field-unlimited-wrapper"]', 1);

    // Test removing items/values on saved entities resets to initial value.
    $this->submitForm([], 'Save');
    $field_2_remove_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $field_1_remove_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $field_0_remove_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $add_more_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSame('', $field_0->getValue());
    $add_more_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSame('', $field_1->getValue());
  }

  /**
   * Tests that no validation occurs on field on "Add more" click.
   */
  public function testFieldMultipleValueWidgetAddMoreNoValidation(): void {
    // Set unlimited field to be required.
    $field_name = 'field_unlimited';
    $field = FieldConfig::loadByName('entity_test', 'entity_test', $field_name);
    $field->setRequired(TRUE);
    $field->save();

    $this->drupalGet('entity_test/add');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add another item with the first item being empty, even though the field
    // is required.
    $add_more_button = $page->findButton('field_unlimited_add_more');
    $add_more_button->click();
    $field_1 = $assert_session->waitForField('field_unlimited[1][value]');
    $this->assertNotEmpty($field_1, 'Successfully added another item.');
    // Confirm the new item has focus.
    $this->assertHasFocusByAttribute('name', 'field_unlimited[1][value]');
    // The first item should not be in error state.
    $assert_session->elementNotExists('css', 'input[name="field_unlimited[0][value]"].error');
  }

  /**
   * Asserts an element specified by an attribute value has focus.
   *
   * @param string $name
   *   The attribute name.
   * @param string $value
   *   The attribute value.
   *
   * @todo Replace with assertHasFocus() in https://drupal.org/i/3041768.
   */
  private function assertHasFocusByAttribute(string $name, string $value): void {
    $active_element = $this->getSession()->evaluateScript('document.activeElement');
    $this->assertSame($value, $active_element->attribute($name));
  }

  /**
   * Test if the quantity items to load is displayed.
   */
  public function testCardinalityDisplayOneField() {
    $this->setCardinalityDisplayHelper('field_unlimited', 3);
    $this->drupalGet('entity_test/add');

    // Check if form loaded the quantity of inputs.
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[1][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[2][value]"]');
    // Only three inputs should be displayed.
    $this->assertSession()->elementNotExists('css', 'input[name="field_unlimited[3][value]"]');

    // Adding one more input.
    $this->clickAddMore('field_unlimited_add_more');
    $this->assertSession()->waitForElement('css', 'input[name="field_unlimited[3][value]"]');
    // Check if only one new input was added.
    $this->assertSession()->elementNotExists('css', 'input[name="field_unlimited[4][value]"]');

    for ($i = 0; $i < 4; $i++) {
      $this->getSession()->getPage()->findField("field_unlimited[{$i}][value]")->setValue($i);
    }

    // Remove the first element.
    $this->clickRemoveItemButton('field_unlimited_0_remove_button');

    // Remove the last element,
    // that became the position 2 after first element removed.
    $this->assertSession()->waitForButton('input[name="field_unlimited_2_remove_button"]');
    $this->clickRemoveItemButton('field_unlimited_2_remove_button');

    // Submit items to validate.
    $this->getSession()
      ->getPage()
      ->findButton('edit-submit')
      ->click();

    // Wait for the page load and check values.
    $this->assertSession()->waitForElement('css', 'input[name="field_unlimited[0][value]"]');
    $firstValue = $this->getSession()->getPage()->findField("field_unlimited[0][value]")->getValue();
    $secondValue = $this->getSession()->getPage()->findField("field_unlimited[1][value]")->getValue();
    // As we remove the first and last items.
    // The remaining values should be 1 and 2.
    $this->assertEquals(1, $firstValue);
    $this->assertEquals(2, $secondValue);

    // Start testing the edit entity behavior.
    // Removing all values only one input should appear.
    $removeButtons = $this->getSession()
      ->getPage()
      ->findAll('css', '[id^="field-unlimited-values"] tbody tr td input[value="Remove"]');
    $this->assertGreaterThan(1, count($removeButtons), 'More than one remove button should exists.');

    for ($i = 0; $i < count($removeButtons); $i++) {
      $this->clickRemoveItemButton('field_unlimited_0_remove_button');
    }

    // Count number of remaining inputs.
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '[id^="field-unlimited-values"] tbody tr td input[value="Remove"]'));
  }

  /**
   * Test cardinality display with drag and drop.
   */
  public function testCardinalityDisplayItemsPreLoadWithDragTable() {
    $this->setCardinalityDisplayHelper('field_unlimited', 3);
    $this->drupalGet('entity_test/add');
    $page = $this->getSession()->getPage();

    // Check quantity items loaded.
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[1][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[2][value]"]');

    $values = [3, 1, 2];
    foreach ($values as $i => $value) {
      $page->findField("field_unlimited[{$i}][value]")->setValue($value);
    }

    // Change number 3 with 1.
    $this->dragItem('tbody tr:nth-child(1) a', 'tbody tr:nth-child(2) a');
    // Change 2 with 3.
    $this->dragItem('tbody tr:nth-child(3) a', 'tbody tr:nth-child(2) a');

    $table = $this->getSession()->getPage()->find('css', '#field-unlimited-values tbody');
    $this->assertEquals(1, $table->find('css', 'tr:nth-child(1) td:nth-child(2) input')->getValue());
    $this->assertEquals(2, $table->find('css', 'tr:nth-child(2) td:nth-child(2) input')->getValue());
    $this->assertEquals(3, $table->find('css', 'tr:nth-child(3) td:nth-child(2) input')->getValue());

    $this->clickAddMore('field_unlimited_add_more');
    $this->assertSession()
      ->waitForElement('css', 'input[name="field_unlimited[3][value]"]')
      ->setValue(1);
    $this->dragItem('tbody tr:nth-child(4) a', 'tbody tr:nth-child(1) a');

    $this->clickAddMore('field_unlimited_add_more');
    $this->assertSession()
      ->waitForElement('css', 'input[name="field_unlimited[4][value]"]')
      ->setValue(5);

    // Submit items to validate.
    $this->getSession()->getPage()->findButton('edit-submit')->click();

    // Check if value and their orders was saved.
    $this->assertSession()->waitForElement('css', 'input[name="field_unlimited[0][value]"]');
    foreach ([1, 1, 2, 3, 5] as $i => $value) {
      $this->assertEquals(
        $value,
        $this->getSession()->getPage()->find('css', 'input[name="field_unlimited[' . $i . '][value]"]')->getValue(),
      );
    }
  }

  /**
   * Test cardinality display with multiple unlimited values on the same form.
   */
  public function testCardinalityDisplayMultipleFields() {
    $this->setCardinalityDisplayHelper('field_unlimited', 2);
    // Add the second field to test multiple fields on the page.
    $field = [
      'field_name' => 'second_unlimited_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => $this->randomMachineName() . '_label',
      'description' => '[site:name]_description',
      'weight' => mt_rand(0, 127),
      'settings' => [
        'test_field_setting' => $this->randomMachineName(),
      ],
    ];
    FieldStorageConfig::create([
      'field_name' => 'second_unlimited_field',
      'entity_type' => 'entity_test',
      'type' => 'test_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'cardinality_display' => 5,
    ])->save();
    FieldConfig::create($field)->save();

    $entity_form_display = EntityFormDisplay::load($field['entity_type'] . '.' . $field['bundle'] . '.default');
    $entity_form_display->setComponent($field['field_name'])->save();

    $this->drupalGet('entity_test/add');

    // Check if form loaded the quantity of inputs.
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[1][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[1][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[2][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[3][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[4][value]"]');

    // Add two inputs to field_unlimited.
    $this->clickAddMore('field_unlimited_add_more');
    $this->assertSession()->waitForElementVisible('css', 'input[name="field_unlimited[2][value]"]');
    $this->clickAddMore('field_unlimited_add_more');
    $this->assertSession()->waitForElementVisible('css', 'input[name="field_unlimited[3][value]"]');

    // Remove last element of the second field
    // and check if all other fields still displaying correctly.
    $this->clickRemoveItemButton("second_unlimited_field_4_remove_button");
    $this->assertSession()->waitForElementVisible('css', '.test', 5 * 10000);
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[1][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[2][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[3][value]"]');
    $this->assertSession()->elementNotExists('css', 'input[name="second_unlimited_field[4][value]"]');

    // Remove all element for both fields.
    // Only one input should appear.
    // Starting removing elements of field_unlimited.
    for ($i = 3; $i >= 0; $i--) {
      $this->clickRemoveItemButton("field_unlimited_{$i}_remove_button");
    }
    // Remove the items from the second field.
    for ($i = 2; $i >= 0; $i--) {
      $this->clickRemoveItemButton("second_unlimited_field_{$i}_remove_button");
    }
    // Check if multiple value fields has only one input.
    $this->assertSession()->elementExists('css', 'input[name="second_unlimited_field[0][value]"]');
    $this->assertSession()->elementExists('css', 'input[name="field_unlimited[0][value]"]');
    $this->assertSession()->elementNotExists('css', 'input[name="field_unlimited[1][value]"]');
    $this->assertSession()->elementNotExists('css', 'input[name="second_unlimited_field[1][value]"]');
  }

  /**
   * Test cardinality display with entity reference fields.
   */
  public function testCardinalityDisplayWithEntityReference() {
    $this->setCardinalityDisplayHelper('field_unlimited', 1);
    $ref_cardinality_display = 2;
    // Crate fake content type and field reference to entity_test.
    $referenced = $this->drupalCreateContentType();
    $field_ref_name = 'ref_unlimited';
    FieldStorageConfig::create([
      'field_name' => $field_ref_name,
      'entity_type' => 'entity_test',
      'translatable' => FALSE,
      'entity_types' => [],
      'settings' => [
        'target_type' => 'entity_test',
      ],
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'cardinality_display' => $ref_cardinality_display,
    ])->save();
    FieldConfig::create([
      'label' => 'Entity reference field',
      'field_name' => $field_ref_name,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'handler' => 'views',
        'handler_settings' => [
          'view' => [
            'view_name' => 'test_entity_reference',
            'display_name' => 'entity_reference_1',
          ],
        ],
      ],
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getViewDisplay('entity_test', 'entity_test')
      ->setComponent($field_ref_name)
      ->save();
    $display_repository->getFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent($field_ref_name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->save();

    // Create nodes to be referenced.
    $n1 = $this->drupalCreateNode([
      'type' => $referenced->id(),
    ]);

    $n2 = $this->drupalCreateNode([
      'type' => $referenced->id(),
    ]);

    $n3 = $this->drupalCreateNode([
      'type' => $referenced->id(),
    ]);

    $view = Views::getView('test_entity_reference');
    $view->setDisplay();
    $fields = $view->displayHandlers->get('entity_reference_1')->getOption('fields');
    $fields['type']['exclude'] = FALSE;
    $view->displayHandlers->get('entity_reference_1')->setOption('fields', $fields);
    $view->save();

    $result = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadByProperties([
        'type' => $referenced->id(),
      ]);

    $this->assertCount(3, $result);

    $this->drupalGet('entity_test/add');
    $this->assertSession()->waitForElementVisible('css', '#edit-ref-unlimited-add-more');
    $assertSession = $this->assertSession();
    for ($i = 0; $i < $ref_cardinality_display; $i++) {
      $locator = "input#edit-{$field_ref_name}-{$i}-target-id";
      $locator = str_replace('_', '-', $locator);
      $assertSession->elementExists('css', $locator);
    }
    // Set value for the first two fields.
    $this->setAutocompleteValue("ref_unlimited[0][target_id]", $n1);
    $this->setAutocompleteValue("ref_unlimited[1][target_id]", $n2);
    // Add one more field and set the third node value.
    $this->clickAddMore("{$field_ref_name}_add_more");
    $this->assertSession()->waitForElement('css', "input[name='ref_unlimited[{$ref_cardinality_display}][target_id]']");
    $this->setAutocompleteValue("ref_unlimited[2][target_id]", $n3);

    $this->clickRemoveItemButton("ref_unlimited_0_remove_button");
    $this->clickRemoveItemButton("ref_unlimited_1_remove_button");
    $this->assertSession()->elementNotExists('css', "input[name='ref_unlimited[1][target_id]']");
    $this->assertSession()->elementNotExists('css', "input[name='ref_unlimited[2][target_id]']");
    $this->assertEquals(
      "{$n2->getTitle()} ({$n2->id()})",
      $this->getSession()->getPage()->findField("ref_unlimited[0][target_id]")->getValue()
    );
  }

  /**
   * Helper function to drag and drop items.
   */
  private function dragItem($from, $to) {
    $currentPage = $this->getSession()
      ->getPage()
      ->find('css', '[id^=field-unlimited-values]');
    $currentPage->find('css', $from)
      ->dragTo($currentPage->find('css', $to));
  }

  /**
   * Helper function to wait and click on add more buttons.
   */
  private function clickAddMore($selector) {
    $this->assertSession()->waitForButton($selector)->click();
  }

  /**
   * Helper function to click on remove item button and wait element gone.
   */
  private function clickRemoveItemButton($locator) {
    $button = $this->getSession()
      ->getPage()
      ->findButton($locator);
    $this->assertNotEmpty($button, "The button {$locator} should exists on the page");
    $button->click();
    $this->assertSession()
      ->waitForElementRemoved('css', "input[name=\"$locator\"]");
  }

  /**
   * Helper function to update field adding cardinality display value.
   */
  private function setCardinalityDisplayHelper($field, $display) {
    $storageConfig = FieldStorageConfig::loadByName('entity_test', $field);
    $storageConfig->setCardinality(FieldStorageConfig::CARDINALITY_UNLIMITED);
    $storageConfig->setCardinalityDisplay($display);
    $storageConfig->save();
  }

  /**
   * Helper function to set autocomplete format to a field.
   */
  private function setAutocompleteValue($field_locator, $node) {
    $this->getSession()->getPage()
      ->findField($field_locator)
      ->setValue("{$node->getTitle()} ({$node->id()})");
  }

}
