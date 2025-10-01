<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Field\Widget;

use Drupal\entity_test\Entity\EntityTestStringId;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests for the machine name field widget.
 *
 * @group Field
 */
class MachineNameWidgetTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'field', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a web user.
    $this->drupalLogin($this->drupalCreateUser(['access content', 'view test entity', 'administer entity_test content', 'administer entity_test_string_id form display']));
  }

  /**
   * Tests the machine name field widget.
   */
  public function testMachineNameWidget(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // First, make sure that both the ID and the label fields are initially
    // hidden in the form display.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test_string_id', 'entity_test_string_id', 'default')
      ->removeComponent('id')
      ->removeComponent('name')
      ->save();

    // Go to the entity add form and check that the test fields are not
    // displayed because they are not configured in the form display yet.
    $this->drupalGet('/entity_test_string_id/add');
    $assert_session->fieldNotExists('id[0][value]');
    $assert_session->fieldNotExists('name[0][value]');

    // Configure the test field to use the machine name widget with no initial
    // settings.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test_string_id', 'entity_test_string_id', 'default')
      ->setComponent('id', [
        'type' => 'machine_name',
        'weight' => 5,
      ])
      ->save();

    // Check that the widget displays an "error" summary when it has missing or
    // broken settings.
    $this->drupalGet('/entity_test_string_id/structure/entity_test_string_id/form-display');
    $assert_session->pageTextContains('Missing configuration.');

    // Check that test field is configured in the form display while the source
    // field is not.
    $this->assertTrue($assert_session->optionExists('fields[id][region]', 'content')->isSelected());
    $this->assertTrue($assert_session->optionExists('fields[name][region]', 'hidden')->isSelected());

    // Open the widget settings form and check that a field which is not present
    // in the form display can not be selected as a 'source_field'.
    $page->pressButton('id_settings_edit');
    $assert_session->waitForField('fields[id][settings_edit_form][settings][source_field]');
    $assert_session->optionNotExists('fields[id][settings_edit_form][settings][source_field]', 'name');

    // Go to the entity add form and check that the test field is not displayed,
    // even when it is enabled in the form display, because of missing settings.
    $this->drupalGet('/entity_test_string_id/add');
    $assert_session->fieldNotExists('id[0][value]');
    $assert_session->fieldNotExists('name[0][value]');

    // Enable the 'source' field in the entity form display and configure the
    // test field to use it.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test_string_id', 'entity_test_string_id', 'default')
      ->setComponent('name', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->save();

    // Go to the form display and check that the machine name widget is only
    // available for the ID field.
    $this->drupalGet('/entity_test_string_id/structure/entity_test_string_id/form-display');
    $assert_session->optionExists('edit-fields-name-type', 'string_textfield');
    $assert_session->optionNotExists('edit-fields-name-type', 'machine_name');

    $assert_session->optionExists('edit-fields-id-type', 'string_textfield');
    $assert_session->optionExists('edit-fields-id-type', 'machine_name');

    // Check that the newly added 'name' field can not be selected as a source
    // field because it has a higher weight in the form display than the field
    // using the machine name widget.
    $page->pressButton('id_settings_edit');
    $assert_session->waitForField('fields[id][settings_edit_form][settings][source_field]');
    $assert_session->optionNotExists('fields[id][settings_edit_form][settings][source_field]', 'name');

    // Configure the source field to have a lower weight than the test field,
    // which will make it appear as an option for the 'Source field' setting.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test_string_id', 'entity_test_string_id', 'default')
      ->setComponent('name', [
        'type' => 'string_textfield',
        'weight' => 4,
      ])
      ->save();

    // Configure the test field to use the newly enabled 'source' field as the
    // machine name source.
    $this->drupalGet('/entity_test_string_id/structure/entity_test_string_id/form-display');
    $page->pressButton('id_settings_edit');
    $assert_session->waitForField('fields[id][settings_edit_form][settings][source_field]');
    $page->selectFieldOption('fields[id][settings_edit_form][settings][source_field]', 'name');
    $page->pressButton('id_plugin_settings_update');
    $assert_session->assertWaitOnAjaxRequest();

    $assert_session->pageTextContains('Source field: Name');
    $assert_session->pageTextContains('Replace pattern: [^a-z0-9_]+');
    $assert_session->pageTextContains('Replace character: _');

    $this->submitForm([], 'Save');
    $assert_session->pageTextContains('Your settings have been saved.');

    $this->drupalGet('/entity_test_string_id/add');
    $test_source_field = $page->findField('name[0][value]');
    $id = $page->findField('id[0][value]');
    $id_machine_name_value = $page->find('css', '#edit-name-0-value-machine-name-suffix .machine-name-value');
    $this->assertNotEmpty($test_source_field);
    $this->assertNotEmpty($id);
    $this->assertNotEmpty($id_machine_name_value, 'Test field with the machine name widget has been initialized.');

    $test_values = [
      'input' => 'Test value !0-9@',
      'expected' => 'test_value_0_9',
    ];
    $test_source_field->setValue($test_values['input']);

    // Wait the set timeout for fetching the machine name.
    $this->assertJsCondition('jQuery("#edit-name-0-value-machine-name-suffix .machine-name-value").html() == "' . $test_values['expected'] . '"');

    // Validate the generated machine name.
    $this->assertEquals($test_values['expected'], $id_machine_name_value->getHtml());

    // Submit the entity form.
    $this->submitForm([], 'Save');

    // Load the entity and check that machine name value that was saved is
    // correct.
    $entity = EntityTestStringId::load('test_value_0_9');
    $this->assertSame($test_values['input'], $entity->get('name')->value);
    $this->assertSame($test_values['expected'], $entity->get('id')->value);

    // Try changing the 'replace_pattern' and 'replace' settings of the widget.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('entity_test_string_id', 'entity_test_string_id', 'default')
      ->setComponent('id', [
        'type' => 'machine_name',
        'weight' => 5,
        'settings' => [
          'source_field' => 'name',
          'replace_pattern' => '[^a-z0-9-]+',
          'replace' => '-',
        ],
      ])
      ->save();

    $this->drupalGet('/entity_test_string_id/add');
    $test_source_field = $page->findField('name[0][value]');
    $id_machine_name_value = $page->find('css', '#edit-name-0-value-machine-name-suffix .machine-name-value');

    $test_values = [
      'input' => 'Test value2 !0-9@',
      'expected' => 'test-value2-0-9',
    ];
    $test_source_field->setValue($test_values['input']);

    // Wait the set timeout for fetching the machine name.
    $this->assertJsCondition('jQuery("#edit-name-0-value-machine-name-suffix .machine-name-value").html() == "' . $test_values['expected'] . '"');

    // Validate the generated machine name.
    $this->assertEquals($test_values['expected'], $id_machine_name_value->getHtml());

    // Submit the entity form.
    $this->submitForm([], 'Save');

    // Load the entity and check that machine name value that was saved is
    // correct.
    $entity = EntityTestStringId::load('test-value2-0-9');
    $this->assertSame($test_values['input'], $entity->get('name')->value);
    $this->assertSame($test_values['expected'], $entity->get('id')->value);

    // Repeat the steps above in order to check that entering an existing value
    // in the machine name widget throws an error.
    $this->drupalGet('/entity_test_string_id/add');
    $test_source_field = $page->findField('name[0][value]');
    $id_machine_name_value = $page->find('css', '#edit-name-0-value-machine-name-suffix .machine-name-value');

    $test_values = [
      'input' => 'Test value2 !0-9@',
      'expected' => 'test-value2-0-9',
    ];
    $test_source_field->setValue($test_values['input']);

    // Wait the set timeout for fetching the machine name.
    $this->assertJsCondition('jQuery("#edit-name-0-value-machine-name-suffix .machine-name-value").html() == "' . $test_values['expected'] . '"');

    // Validate the generated machine name.
    $this->assertEquals($test_values['expected'], $id_machine_name_value->getHtml());

    // Submit the entity form.
    $this->submitForm([], 'Save');

    // Check that a form-level error has been thrown.
    $assert_session->pageTextContains('A test entity with string_id with id test-value2-0-9- already exists.');
  }

}
