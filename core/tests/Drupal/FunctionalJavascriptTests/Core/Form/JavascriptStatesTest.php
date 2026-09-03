<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Form;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the state of elements based on another elements.
 *
 * The form being tested is JavascriptStatesForm provided by the 'form_test'
 * module under 'system' (core/modules/system/tests/module/form_test).
 *
 * @see Drupal\form_test\Form\JavascriptStatesForm
 */
#[Group('javascript')]
#[Group('#slow')]
#[RunTestsInSeparateProcesses]
class JavascriptStatesTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add text formats.
    $filtered_html_format = FilterFormat::create([
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => [],
    ]);
    $filtered_html_format->save();
    $full_html_format = FilterFormat::create([
      'format' => 'full_html',
      'name' => 'Full HTML',
      'weight' => 1,
      'filters' => [],
    ]);
    $full_html_format->save();
    $normal_user = $this->drupalCreateUser([
      'use text format filtered_html',
      'use text format full_html',
    ]);
    $this->drupalLogin($normal_user);
  }

  /**
   * Tests the JavaScript #states functionality of form elements.
   *
   * To avoid the large cost of a dataProvider in FunctionalJavascript tests,
   * this is a single public test method that invokes a series of protected
   * methods to do assertions on specific kinds of triggering elements.
   */
  public function testJavascriptStates(): void {
    $this->doCheckboxTriggerTests();
    $this->doCheckboxesTriggerTests();
    $this->doTextfieldTriggerTests();
    $this->doRadiosTriggerTests();
    $this->doSelectTriggerTests();
    $this->doMultipleSelectTriggerTests();
    $this->doMultipleTriggerTests();
    $this->doNestedTriggerTests();
    $this->doElementsDisabledStateTests();
    $this->doAjaxAffectedTriggerTests();
    $this->doAjaxDependentCleanupTests();
  }

  /**
   * Tests states of elements triggered by a checkbox element.
   */
  protected function doCheckboxTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $trigger = $page->findField('checkbox_trigger');
    $this->assertNotEmpty($trigger);
    $textfield_invisible_element = $page->findField('textfield_invisible_when_checkbox_trigger_checked');
    $this->assertNotEmpty($textfield_invisible_element);
    $textfield_required_element = $page->findField('textfield_required_when_checkbox_trigger_checked');
    $this->assertNotEmpty($textfield_required_element);
    $textfield_readonly_element = $page->findField('textfield_readonly_when_checkbox_trigger_checked');
    $this->assertNotEmpty($textfield_readonly_element);
    $textarea_readonly_element = $page->findField('textarea_readonly_when_checkbox_trigger_checked');
    $this->assertNotEmpty($textarea_readonly_element);
    $details = $this->assertSession()->elementExists('css', '#edit-details-expanded-when-checkbox-trigger-checked');
    $textfield_in_details = $details->findField('textfield_in_details');
    $this->assertNotEmpty($textfield_in_details);
    $checkbox_checked_element = $page->findField('checkbox_checked_when_checkbox_trigger_checked');
    $this->assertNotEmpty($checkbox_checked_element);
    $checkbox_unchecked_element = $page->findField('checkbox_unchecked_when_checkbox_trigger_checked');
    $this->assertNotEmpty($checkbox_unchecked_element);
    $checkbox_visible_element = $page->findField('checkbox_visible_when_checkbox_trigger_checked');
    $this->assertNotEmpty($checkbox_visible_element);
    $text_format_invisible_value = $page->findField('text_format_invisible_when_checkbox_trigger_checked[value]');
    $this->assertNotEmpty($text_format_invisible_value);
    $text_format_invisible_format = $page->findField('text_format_invisible_when_checkbox_trigger_checked[format]');
    $this->assertNotEmpty($text_format_invisible_format);
    $link = $page->findLink('Link states test');

    $checkboxes_all_checked_element_value1 = $page->findField('checkboxes_all_checked_when_checkbox_trigger_checked[value1]');
    $this->assertNotEmpty($checkboxes_all_checked_element_value1);
    $checkboxes_all_checked_element_value2 = $page->findField('checkboxes_all_checked_when_checkbox_trigger_checked[value2]');
    $this->assertNotEmpty($checkboxes_all_checked_element_value2);
    $checkboxes_all_checked_element_value3 = $page->findField('checkboxes_all_checked_when_checkbox_trigger_checked[value3]');
    $this->assertNotEmpty($checkboxes_all_checked_element_value3);

    $checkboxes_some_checked_element_value1 = $page->findField('checkboxes_some_checked_when_checkbox_trigger_checked[value1]');
    $this->assertNotEmpty($checkboxes_some_checked_element_value1);
    $checkboxes_some_checked_element_value2 = $page->findField('checkboxes_some_checked_when_checkbox_trigger_checked[value2]');
    $this->assertNotEmpty($checkboxes_some_checked_element_value2);
    $checkboxes_some_checked_element_value3 = $page->findField('checkboxes_some_checked_when_checkbox_trigger_checked[value3]');
    $this->assertNotEmpty($checkboxes_some_checked_element_value3);

    $checkboxes_all_disabled_element_value1 = $page->findField('checkboxes_all_disabled_when_checkbox_trigger_checked[value1]');
    $this->assertNotEmpty($checkboxes_all_disabled_element_value1);
    $checkboxes_all_disabled_element_value2 = $page->findField('checkboxes_all_disabled_when_checkbox_trigger_checked[value2]');
    $this->assertNotEmpty($checkboxes_all_disabled_element_value2);
    $checkboxes_all_disabled_element_value3 = $page->findField('checkboxes_all_disabled_when_checkbox_trigger_checked[value3]');
    $this->assertNotEmpty($checkboxes_all_disabled_element_value3);

    $checkboxes_some_disabled_element_value1 = $page->findField('checkboxes_some_disabled_when_checkbox_trigger_checked[value1]');
    $this->assertNotEmpty($checkboxes_some_disabled_element_value1);
    $checkboxes_some_disabled_element_value2 = $page->findField('checkboxes_some_disabled_when_checkbox_trigger_checked[value2]');
    $this->assertNotEmpty($checkboxes_some_disabled_element_value2);
    $checkboxes_some_disabled_element_value3 = $page->findField('checkboxes_some_disabled_when_checkbox_trigger_checked[value3]');

    $radios_checked_element = $page->findField('radios_checked_when_checkbox_trigger_checked');
    $this->assertNotEmpty($radios_checked_element);

    // We want to select the specific radio buttons, not the whole radios field
    // itself.
    $radios_all_disabled_value1 = $this->xpath(
      '//input[@name=:name][@value=:value]',
      [':name' => 'radios_all_disabled_when_checkbox_trigger_checked', ':value' => 'value1']);
    $this->assertCount(1, $radios_all_disabled_value1);
    // We want to access the radio button directly for the rest of the test, so
    // take it out of the array we got back from xpath().
    $radios_all_disabled_value1 = reset($radios_all_disabled_value1);
    $radios_all_disabled_value2 = $this->xpath(
      '//input[@name=:name][@value=:value]',
      [':name' => 'radios_all_disabled_when_checkbox_trigger_checked', ':value' => 'value2']);
    $this->assertCount(1, $radios_all_disabled_value2);
    $radios_all_disabled_value2 = reset($radios_all_disabled_value2);

    $radios_some_disabled_value1 = $this->xpath(
      '//input[@name=:name][@value=:value]',
      [':name' => 'radios_some_disabled_when_checkbox_trigger_checked', ':value' => 'value1']);
    $this->assertCount(1, $radios_some_disabled_value1);
    $radios_some_disabled_value1 = reset($radios_some_disabled_value1);
    $radios_some_disabled_value2 = $this->xpath(
      '//input[@name=:name][@value=:value]',
      [':name' => 'radios_some_disabled_when_checkbox_trigger_checked', ':value' => 'value2']);
    $this->assertCount(1, $radios_some_disabled_value2);
    $radios_some_disabled_value2 = reset($radios_some_disabled_value2);

    // Verify initial state.
    $this->assertTrue($textfield_invisible_element->isVisible());
    $this->assertFalse($details->hasAttribute('open'));
    $this->assertFalse($textfield_in_details->isVisible());
    $this->assertFalse($textfield_required_element->hasAttribute('required'));
    $this->assertFalse($textfield_required_element->hasAttribute('aria-required'));
    $this->assertFalse($textfield_readonly_element->hasAttribute('readonly'));
    $this->assertFalse($textarea_readonly_element->hasAttribute('readonly'));
    $this->assertFalse($checkbox_checked_element->isChecked());
    $this->assertTrue($checkbox_unchecked_element->isChecked());
    $this->assertFalse($checkbox_visible_element->isVisible());
    $this->assertTrue($text_format_invisible_value->isVisible());
    $this->assertTrue($text_format_invisible_format->isVisible());
    $this->assertFalse($checkboxes_all_checked_element_value1->isChecked());
    $this->assertFalse($checkboxes_all_checked_element_value2->isChecked());
    $this->assertFalse($checkboxes_all_checked_element_value3->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value1->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value2->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value3->isChecked());
    $this->assertFalse($checkboxes_all_disabled_element_value1->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_all_disabled_element_value2->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_all_disabled_element_value3->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value1->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value2->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value3->hasAttribute('disabled'));
    $this->assertFalse($radios_checked_element->isChecked());
    $this->assertEquals(NULL, $radios_checked_element->getValue());
    $this->assertFalse($radios_all_disabled_value1->hasAttribute('disabled'));
    $this->assertFalse($radios_all_disabled_value2->hasAttribute('disabled'));
    $this->assertFalse($radios_some_disabled_value1->hasAttribute('disabled'));
    $this->assertFalse($radios_some_disabled_value2->hasAttribute('disabled'));
    // Check if the link is visible.
    $this->assertTrue($link->isVisible());
    // Check enter password is visible.
    $this->assertSession()->pageTextContains('Enter password');

    // Change state: check the checkbox.
    $trigger->check();
    // Verify triggered state.
    $this->assertFalse($textfield_invisible_element->isVisible());
    $this->assertEquals('required', $textfield_required_element->getAttribute('required'));
    $this->assertFalse($textfield_required_element->hasAttribute('aria-required'));
    $this->assertTrue($textfield_readonly_element->hasAttribute('readonly'));
    $this->assertTrue($textarea_readonly_element->hasAttribute('readonly'));
    $this->assertTrue($details->hasAttribute('open'));
    $this->assertTrue($textfield_in_details->isVisible());
    $this->assertTrue($checkbox_checked_element->isChecked());
    $this->assertFalse($checkbox_unchecked_element->isChecked());
    $this->assertTrue($checkbox_visible_element->isVisible());
    $this->assertFalse($text_format_invisible_value->isVisible());
    $this->assertFalse($text_format_invisible_format->isVisible());
    // All 3 of the other set should be checked.
    $this->assertTrue($checkboxes_all_checked_element_value1->isChecked());
    $this->assertTrue($checkboxes_all_checked_element_value2->isChecked());
    $this->assertTrue($checkboxes_all_checked_element_value3->isChecked());
    // Value 1 and 3 should now be checked.
    $this->assertTrue($checkboxes_some_checked_element_value1->isChecked());
    $this->assertTrue($checkboxes_some_checked_element_value3->isChecked());
    // Only value 2 should remain unchecked.
    $this->assertFalse($checkboxes_some_checked_element_value2->isChecked());
    // All 3 of these should be disabled.
    $this->assertTrue($checkboxes_all_disabled_element_value1->hasAttribute('disabled'));
    $this->assertTrue($checkboxes_all_disabled_element_value2->hasAttribute('disabled'));
    $this->assertTrue($checkboxes_all_disabled_element_value3->hasAttribute('disabled'));
    // Only values 1 and 3 should be disabled, 2 should still be enabled.
    $this->assertTrue($checkboxes_some_disabled_element_value1->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value2->hasAttribute('disabled'));
    $this->assertTrue($checkboxes_some_disabled_element_value3->hasAttribute('disabled'));
    $this->assertEquals('value1', $radios_checked_element->getValue());
    // Both of these should now be disabled.
    $this->assertTrue($radios_all_disabled_value1->hasAttribute('disabled'));
    $this->assertTrue($radios_all_disabled_value2->hasAttribute('disabled'));
    // Only value1 should be disabled, value 2 should remain enabled.
    $this->assertTrue($radios_some_disabled_value1->hasAttribute('disabled'));
    $this->assertFalse($radios_some_disabled_value2->hasAttribute('disabled'));
    // The link shouldn't be visible.
    $this->assertFalse($link->isVisible());
    // Check enter password is not visible.
    $this->assertSession()->pageTextNotContains('Enter password');

    // Change state: uncheck the checkbox.
    $trigger->uncheck();
    // Verify triggered state, which should match the initial state.
    $this->assertTrue($textfield_invisible_element->isVisible());
    $this->assertFalse($details->hasAttribute('open'));
    $this->assertFalse($textfield_in_details->isVisible());
    $this->assertFalse($textfield_required_element->hasAttribute('required'));
    $this->assertFalse($textfield_readonly_element->hasAttribute('readonly'));
    $this->assertFalse($textarea_readonly_element->hasAttribute('readonly'));
    $this->assertFalse($checkbox_checked_element->isChecked());
    $this->assertTrue($checkbox_unchecked_element->isChecked());
    $this->assertFalse($checkbox_visible_element->isVisible());
    $this->assertTrue($text_format_invisible_value->isVisible());
    $this->assertTrue($text_format_invisible_format->isVisible());
    $this->assertFalse($checkboxes_all_checked_element_value1->isChecked());
    $this->assertFalse($checkboxes_all_checked_element_value2->isChecked());
    $this->assertFalse($checkboxes_all_checked_element_value3->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value1->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value2->isChecked());
    $this->assertFalse($checkboxes_some_checked_element_value3->isChecked());
    $this->assertFalse($checkboxes_all_disabled_element_value1->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_all_disabled_element_value2->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_all_disabled_element_value3->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value1->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value2->hasAttribute('disabled'));
    $this->assertFalse($checkboxes_some_disabled_element_value3->hasAttribute('disabled'));
    $this->assertFalse($radios_checked_element->isChecked());
    $this->assertEquals(NULL, $radios_checked_element->getValue());
    $this->assertFalse($radios_all_disabled_value1->hasAttribute('disabled'));
    $this->assertFalse($radios_all_disabled_value2->hasAttribute('disabled'));
    $this->assertFalse($radios_some_disabled_value1->hasAttribute('disabled'));
    $this->assertFalse($radios_some_disabled_value2->hasAttribute('disabled'));
    // Check if the link is turned back to visible state.
    $this->assertTrue($link->isVisible());
    // Check enter password is visible.
    $this->assertSession()->pageTextContains('Enter password');
  }

  /**
   * Tests states of elements triggered by a checkboxes element.
   */
  protected function doCheckboxesTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $trigger_value1 = $page->findField('checkboxes_trigger[value1]');
    $this->assertNotEmpty($trigger_value1);
    $trigger_value2 = $page->findField('checkboxes_trigger[value2]');
    $this->assertNotEmpty($trigger_value2);
    $trigger_value3 = $page->findField('checkboxes_trigger[value3]');
    $this->assertNotEmpty($trigger_value3);
    $textfield_visible_value2 = $page->findField('textfield_visible_when_checkboxes_trigger_value2_checked');
    $this->assertNotEmpty($textfield_visible_value2);
    $textfield_visible_value3 = $page->findField('textfield_visible_when_checkboxes_trigger_value3_checked');
    $this->assertNotEmpty($textfield_visible_value3);

    // Verify initial state.
    $this->assertFalse($textfield_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    // Change state: check the 'Value 1' checkbox.
    $trigger_value1->check();
    $this->assertFalse($textfield_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    // Change state: check the 'Value 2' checkbox.
    $trigger_value2->check();
    $this->assertTrue($textfield_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    // Change state: check the 'Value 3' checkbox.
    $trigger_value3->check();
    $this->assertTrue($textfield_visible_value2->isVisible());
    $this->assertTrue($textfield_visible_value3->isVisible());
    // Change state: uncheck the 'Value 2' checkbox.
    $trigger_value2->uncheck();
    $this->assertFalse($textfield_visible_value2->isVisible());
    $this->assertTrue($textfield_visible_value3->isVisible());
  }

  /**
   * Tests states of elements triggered by a textfield element.
   */
  protected function doTextfieldTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $trigger = $page->findField('textfield_trigger');
    $this->assertNotEmpty($trigger);
    $checkbox_checked_target = $page->findField('checkbox_checked_when_textfield_trigger_filled');
    $this->assertNotEmpty($checkbox_checked_target);
    $checkbox_unchecked_target = $page->findField('checkbox_unchecked_when_textfield_trigger_filled');
    $this->assertNotEmpty($checkbox_unchecked_target);
    $select_invisible_target = $page->findField('select_invisible_when_textfield_trigger_filled');
    $this->assertNotEmpty($select_invisible_target);
    $select_visible_target = $page->findField('select_visible_when_textfield_trigger_filled');
    $this->assertNotEmpty($select_visible_target);
    $textfield_required_target = $page->findField('textfield_required_when_textfield_trigger_filled');
    $this->assertNotEmpty($textfield_required_target);
    $details = $this->assertSession()->elementExists('css', '#edit-details-expanded-when-textfield-trigger-filled');
    $textfield_in_details = $details->findField('textfield_in_details');
    $this->assertNotEmpty($textfield_in_details);

    // Verify initial state.
    $this->assertFalse($checkbox_checked_target->isChecked());
    $this->assertTrue($checkbox_unchecked_target->isChecked());
    $this->assertTrue($select_invisible_target->isVisible());
    $this->assertFalse($select_visible_target->isVisible());
    $this->assertFalse($textfield_required_target->hasAttribute('required'));
    $this->assertFalse($textfield_required_target->hasAttribute('aria-required'));
    $this->assertFalse($details->hasAttribute('open'));
    $this->assertFalse($textfield_in_details->isVisible());

    // Change state: fill the textfield.
    $trigger->setValue('filled');
    // Verify triggered state.
    $this->assertTrue($checkbox_checked_target->isChecked());
    $this->assertFalse($checkbox_unchecked_target->isChecked());
    $this->assertFalse($select_invisible_target->isVisible());
    $this->assertTrue($select_visible_target->isVisible());
    $this->assertEquals('required', $textfield_required_target->getAttribute('required'));
    $this->assertFalse($textfield_required_target->hasAttribute('aria-required'));
    $this->assertTrue($details->hasAttribute('open'));
    $this->assertTrue($textfield_in_details->isVisible());
  }

  /**
   * Tests states of elements triggered by a radios element.
   */
  protected function doRadiosTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $trigger = $page->findField('radios_trigger');
    $this->assertNotEmpty($trigger);
    $fieldset_visible_when_value2 = $this->assertSession()->elementExists('css', '#edit-fieldset-visible-when-radios-trigger-has-value2');
    $textfield_in_fieldset = $fieldset_visible_when_value2->findField('textfield_in_fieldset');
    $this->assertNotEmpty($textfield_in_fieldset);
    $checkbox_checked_target = $page->findField('checkbox_checked_when_radios_trigger_has_value3');
    $this->assertNotEmpty($checkbox_checked_target);
    $checkbox_unchecked_target = $page->findField('checkbox_unchecked_when_radios_trigger_has_value3');
    $this->assertNotEmpty($checkbox_unchecked_target);
    $textfield_invisible_target = $page->findField('textfield_invisible_when_radios_trigger_has_value2');
    $this->assertNotEmpty($textfield_invisible_target);
    $select_required_target = $page->findField('select_required_when_radios_trigger_has_value2');
    $this->assertNotEmpty($select_required_target);
    $details = $this->assertSession()->elementExists('css', '#edit-details-expanded-when-radios-trigger-has-value3');
    $textfield_in_details = $details->findField('textfield_in_details');
    $this->assertNotEmpty($textfield_in_details);

    // Verify initial state, both the fieldset and something inside it.
    $this->assertFalse($fieldset_visible_when_value2->isVisible());
    $this->assertFalse($textfield_in_fieldset->isVisible());
    $this->assertFalse($checkbox_checked_target->isChecked());
    $this->assertTrue($checkbox_unchecked_target->isChecked());
    $this->assertTrue($textfield_invisible_target->isVisible());
    $this->assertFalse($select_required_target->hasAttribute('required'));
    $this->assertFalse($select_required_target->hasAttribute('aria-required'));
    $this->assertFalse($details->hasAttribute('open'));
    $this->assertFalse($textfield_in_details->isVisible());

    // Change state: select the value2 radios option.
    $trigger->selectOption('value2');
    // Verify triggered state.
    $this->assertTrue($fieldset_visible_when_value2->isVisible());
    $this->assertTrue($textfield_in_fieldset->isVisible());
    $this->assertFalse($textfield_invisible_target->isVisible());
    $this->assertTrue($select_required_target->hasAttribute('required'));
    $this->assertFalse($select_required_target->hasAttribute('aria-required'));
    // Checkboxes and details should not have changed state, yet.
    $this->assertFalse($checkbox_checked_target->isChecked());
    $this->assertTrue($checkbox_unchecked_target->isChecked());
    $this->assertFalse($details->hasAttribute('open'));
    $this->assertFalse($textfield_in_details->isVisible());
    // Change state: select the value3 radios option.
    $trigger->selectOption('value3');
    // Fieldset and contents should re-disappear.
    $this->assertFalse($fieldset_visible_when_value2->isVisible());
    $this->assertFalse($textfield_in_fieldset->isVisible());
    // Textfield and select should revert to initial state.
    $this->assertTrue($textfield_invisible_target->isVisible());
    $this->assertFalse($select_required_target->hasAttribute('required'));
    $this->assertFalse($select_required_target->hasAttribute('aria-required'));
    // Checkbox states should now change.
    $this->assertTrue($checkbox_checked_target->isChecked());
    $this->assertFalse($checkbox_unchecked_target->isChecked());
    // Details should now be expanded.
    $this->assertTrue($details->hasAttribute('open'));
    $this->assertTrue($textfield_in_details->isVisible());
  }

  /**
   * Tests states of elements triggered by a select element.
   */
  protected function doSelectTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $trigger = $page->findField('select_trigger');
    $this->assertNotEmpty($trigger);
    $item_visible_value2 = $this->assertSession()->elementExists('css', '#edit-item-visible-when-select-trigger-has-value2');
    $textfield_visible_value3 = $page->findField('textfield_visible_when_select_trigger_has_value3');
    $this->assertNotEmpty($textfield_visible_value3);
    $textfield_visible_value2_or_value3 = $page->findField('textfield_visible_when_select_trigger_has_value2_or_value3');
    $this->assertNotEmpty($textfield_visible_value2_or_value3);

    // Verify initial state.
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_or_value3->isVisible());
    // Change state: select the 'Value 2' option.
    $trigger->setValue('value2');
    $this->assertTrue($item_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertTrue($textfield_visible_value2_or_value3->isVisible());
    // Change state: select the 'Value 3' option.
    $trigger->setValue('value3');
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertTrue($textfield_visible_value3->isVisible());
    $this->assertTrue($textfield_visible_value2_or_value3->isVisible());

    $this->container->get('module_installer')->install(['big_pipe']);
    $this->drupalGet('form-test/javascript-states-form');
    $select_visible_2 = $this->assertSession()->elementExists('css', 'select[name="select_visible_2"]');
    $select_visible_3 = $this->assertSession()->elementExists('css', 'select[name="select_visible_3"]');
    $this->assertFalse($select_visible_3->isVisible());

    $select_visible_2->setValue('1');
    $this->assertTrue($select_visible_3->isVisible());
  }

  /**
   * Tests states of elements triggered by a multiple select element.
   */
  protected function doMultipleSelectTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();
    // Find trigger and target elements.
    $trigger = $page->findField('multiple_select_trigger[]');
    $this->assertNotEmpty($trigger);
    $item_visible_value2 = $this->assertSession()->elementExists('css', '#edit-item-visible-when-multiple-select-trigger-has-value2');
    $item_visible_no_value = $this->assertSession()->elementExists('css', '#edit-item-visible-when-multiple-select-trigger-has-no-value');
    $textfield_visible_value3 = $page->findField('textfield_visible_when_multiple_select_trigger_has_value3');
    $this->assertNotEmpty($textfield_visible_value3);
    $textfield_visible_value2_or_value3 = $page->findField('textfield_visible_when_multiple_select_trigger_has_value2_or_value3');
    $this->assertNotEmpty($textfield_visible_value2_or_value3);
    $textfield_visible_value2_and_value3 = $page->findField('textfield_visible_when_multiple_select_trigger_has_value2_and_value3');
    $this->assertNotEmpty($textfield_visible_value2_and_value3);

    // Verify initial state.
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertTrue($item_visible_no_value->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_or_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_and_value3->isVisible());
    // Change state: select the 'Value 2' option.
    $trigger->setValue('value2');
    $this->assertTrue($item_visible_value2->isVisible());
    $this->assertFalse($item_visible_no_value->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertTrue($textfield_visible_value2_or_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_and_value3->isVisible());
    // Change state: select the 'Value 3' option.
    $trigger->setValue('value3');
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertFalse($item_visible_no_value->isVisible());
    $this->assertTrue($textfield_visible_value3->isVisible());
    $this->assertTrue($textfield_visible_value2_or_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_and_value3->isVisible());
    // Change state: select 'Value2' and 'Value 3' options.
    $trigger->setValue(['value2', 'value3']);
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertFalse($item_visible_no_value->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_or_value3->isVisible());
    $this->assertTrue($textfield_visible_value2_and_value3->isVisible());
    // Restore initial trigger state (clear the values).
    $trigger->setValue([]);
    // Make sure the initial element states are restored.
    $this->assertFalse($item_visible_value2->isVisible());
    $this->assertFalse($textfield_visible_value3->isVisible());
    $this->assertFalse($textfield_visible_value2_or_value3->isVisible());
    // @todo These last two look to be correct, but the assertion is failing.
    // @see https://www.drupal.org/project/drupal/issues/3367310
    // $this->assertTrue($item_visible_no_value->isVisible());
    // $this->assertFalse($textfield_visible_value2_and_value3->isVisible());
  }

  /**
   * Tests states of elements triggered by multiple elements.
   */
  protected function doMultipleTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $select_trigger = $page->findField('select_trigger');
    $this->assertNotEmpty($select_trigger);
    $textfield_trigger = $page->findField('textfield_trigger');
    $this->assertNotEmpty($textfield_trigger);
    $item_visible_value2_and_textfield = $this->assertSession()->elementExists('css', '#edit-item-visible-when-select-trigger-has-value2-and-textfield-trigger-filled');

    // Verify initial state.
    $this->assertFalse($item_visible_value2_and_textfield->isVisible());
    // Change state: select the 'Value 2' option.
    $select_trigger->setValue('value2');
    $this->assertFalse($item_visible_value2_and_textfield->isVisible());
    // Change state: fill the textfield.
    $textfield_trigger->setValue('filled');
    $this->assertTrue($item_visible_value2_and_textfield->isVisible());
  }

  /**
   * Tests states of radios element triggered by other radios element.
   */
  protected function doNestedTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $radios_opposite1 = $page->findField('radios_opposite1');
    $this->assertNotEmpty($radios_opposite1);
    $radios_opposite2 = $page->findField('radios_opposite2');
    $this->assertNotEmpty($radios_opposite2);

    // Verify initial state.
    $this->assertEquals('0', $radios_opposite1->getValue());
    $this->assertEquals('1', $radios_opposite2->getValue());

    // Set $radios_opposite2 value to 0, $radios_opposite1 value should be 1.
    $radios_opposite2->setValue('0');
    $this->assertEquals('1', $radios_opposite1->getValue());

    // Set $radios_opposite1 value to 1, $radios_opposite2 value should be 0.
    $radios_opposite1->setValue('0');
    $this->assertEquals('1', $radios_opposite2->getValue());
  }

  /**
   * Tests the submit button, select and textarea disabled states.
   *
   * The element should be disabled when visit the form
   * then they should enable when trigger by a checkbox.
   */
  public function doElementsDisabledStateTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $session = $this->assertSession();

    // The submit button should be disabled when visit the form.
    $button = $session->elementExists('css', 'input[value="Submit button disabled when checkbox not checked"]');
    $this->assertTrue($button->hasAttribute('disabled'));

    // The submit button should be enabled when the checkbox is checked.
    $session->elementExists('css', 'input[name="checkbox_enable_submit_button"]')->check();
    $this->assertFalse($button->hasAttribute('disabled'));

    // The text field should be disabled when visit the form.
    $textfield = $session->elementExists('css', 'input[name="input_textfield"]');
    $this->assertTrue($textfield->hasAttribute('disabled'));

    // The text field should be enabled when the checkbox is checked.
    $session->elementExists('css', 'input[name="checkbox_enable_input_textfield"]')->check();
    $this->assertFalse($textfield->hasAttribute('disabled'));

    // The select should be disabled when visit the form.
    $select = $session->elementExists('css', 'select[name="test_select_disabled"]');
    $this->assertTrue($select->hasAttribute('disabled'));

    // The select should be enabled when the checkbox is checked.
    $session->elementExists('css', 'input[name="checkbox_enable_select"]')->check();
    $this->assertFalse($select->hasAttribute('disabled'));

    // The textarea should be disabled when visit the form.
    $textarea = $session->elementExists('css', 'textarea[name="test_textarea_disabled"]');
    $this->assertTrue($textarea->hasAttribute('disabled'));

    // The textarea should be enabled when the checkbox is checked.
    $session->elementExists('css', 'input[name="checkbox_enable_textarea"]')->check();
    $this->assertFalse($textarea->hasAttribute('disabled'));
  }

  /**
   * Tests states of elements affected by ajax.
   */
  protected function doAjaxAffectedTriggerTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();

    // Find trigger and target elements.
    $ajax_reload = $page->findField('ajax_reload');
    $this->assertNotEmpty($ajax_reload);
    $shared_ajax_states_trigger = $page->findField('shared_ajax_states_trigger');
    $this->assertNotEmpty($shared_ajax_states_trigger);
    $checkbox_trigger = $page->findField('checkbox_trigger');
    $this->assertNotEmpty($checkbox_trigger);
    $shared_checkbox_trigger = $page->findField('shared_checkbox_trigger');
    $this->assertNotEmpty($shared_checkbox_trigger);
    $shared_textfield_trigger = $page->findField('shared_textfield_trigger');
    $this->assertNotEmpty($shared_textfield_trigger);
    $not_ajax_select_trigger = $page->findField('not_ajax_select_trigger');
    $this->assertNotEmpty($not_ajax_select_trigger);
    $ajax_affected_select_trigger = $page->findField('ajax_select_trigger');
    $this->assertNotEmpty($ajax_affected_select_trigger);
    $ajax_added_trigger = $page->findField('ajax_added_trigger');
    $this->assertEmpty($ajax_added_trigger);
    $ajax_set_trigger = $page->findField('ajax_set_trigger');
    $this->assertNotEmpty($ajax_set_trigger);
    $ajax_removed_textfield_with_shared_trigger = $page->findField('ajax_removed_textfield_with_shared_trigger');
    $this->assertNotEmpty($ajax_removed_textfield_with_shared_trigger);
    $ajax_removed_textfield_with_shared_trigger_state = $page->findField('ajax_removed_textfield_with_shared_trigger_state');
    $this->assertNotEmpty($ajax_removed_textfield_with_shared_trigger_state);
    $ajax_added_textfield_enabled_when_checkbox_trigger_checked = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($ajax_added_textfield_enabled_when_checkbox_trigger_checked);
    $textfield_enabled_when_shared_checkbox_trigger_checked = $page->findField('textfield_enabled_when_shared_checkbox_trigger_checked');
    $this->assertNotEmpty($textfield_enabled_when_shared_checkbox_trigger_checked);
    $textfield_enabled_when_shared_textfield_trigger_value = $page->findField('textfield_enabled_when_shared_textfield_trigger_value');
    $this->assertNotEmpty($textfield_enabled_when_shared_textfield_trigger_value);
    $textfield_enabled_when_checkbox_trigger_is_checked = $page->findField('textfield_enabled_when_checkbox_trigger_is_checked');
    $this->assertNotEmpty($textfield_enabled_when_checkbox_trigger_is_checked);
    $textfield_visible_ajax_single = $page->findField('textfield_visible_when_ajax_select_trigger_is_1');
    $this->assertNotEmpty($textfield_visible_ajax_single);
    $textfield_visible_not_ajax_single = $page->findField('textfield_visible_when_not_ajax_select_trigger_is_1');
    $this->assertNotEmpty($textfield_visible_not_ajax_single);
    $textfield_visible_multiple = $page->findField('textfield_visible_when_ajax_trigger_is_1_and_not_ajax_trigger_is_1');
    $this->assertNotEmpty($textfield_visible_multiple);
    $textfield_invisible_single_ajax_added = $page->findField('textfield_invisible_when_ajax_added_trigger_is_checked');
    $this->assertNotEmpty($textfield_invisible_single_ajax_added);
    $textfield_invisible_multiple_ajax_added_and = $page->findField('textfield_invisible_when_ajax_added_trigger_is_checked_and_not_ajax_trigger_is_1');
    $this->assertNotEmpty($textfield_invisible_multiple_ajax_added_and);
    $textfield_invisible_multiple_ajax_added_or = $page->findField('textfield_invisible_when_ajax_added_trigger_is_checked_or_not_ajax_trigger_is_1');
    $this->assertNotEmpty($textfield_invisible_multiple_ajax_added_or);
    $textfield_visible_single_ajax_added = $page->findField('textfield_visible_when_ajax_added_trigger_is_checked_form_selector');
    $this->assertNotEmpty($textfield_visible_single_ajax_added);
    $textfield_visible_single_ajax_set = $page->findField('textfield_visible_when_ajax_set_trigger_is_value1_form_selector');
    $this->assertNotEmpty($textfield_visible_single_ajax_set);
    $ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1 = $page->findField('ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1');
    $this->assertEmpty($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1);

    // Verify initial state.
    $this->assertEquals('', $shared_ajax_states_trigger->getValue());
    $this->assertEquals('', $ajax_set_trigger->getValue());
    $this->assertFalse($shared_checkbox_trigger->isChecked());
    $this->assertEquals('', $shared_textfield_trigger->getValue());
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger_state->hasAttribute('disabled'));
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Change state: select the ajax_affected_select_trigger 1 option.
    $ajax_affected_select_trigger->setValue(1);
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    // $textfield_visible_ajax_single is now visible.
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Change state: select the select_trigger value2 option.
    $not_ajax_select_trigger->setValue(1);
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    // $textfield_visible_not_ajax_single is now visible.
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    // $textfield_visible_multiple is now visible.
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    // $textfield_invisible_multiple_ajax_added_or is now invisible.
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Trigger Ajax reload.
    $ajax_reload->check();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // $ajax_removed_textfield_with_shared_trigger is removed from the DOM.
    $ajax_removed_textfield_with_shared_trigger = $page->findField('ajax_removed_textfield_with_shared_trigger');
    $this->assertEmpty($ajax_removed_textfield_with_shared_trigger);
    // $ajax_removed_textfield_with_shared_trigger_state is removed from the DOM.
    $ajax_removed_textfield_with_shared_trigger_state = $page->findField('ajax_removed_textfield_with_shared_trigger_state');
    $this->assertEmpty($ajax_removed_textfield_with_shared_trigger_state);
    // $ajax_added_trigger is now present in the dom.
    $ajax_added_trigger = $page->findField('ajax_added_trigger');
    $this->assertNotEmpty($ajax_added_trigger);
    // $ajax_set_trigger should now be set to value1
    $this->assertEquals('value1', $ajax_set_trigger->getValue());
    // There is no change to values or visibility.
    $this->assertEquals(1, $ajax_affected_select_trigger->getValue());
    $this->assertEquals(1, $not_ajax_select_trigger->getValue());
    $this->assertEquals(0, $ajax_added_trigger->getValue());
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    // $textfield_visible_single_ajax_set is now visible.
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    // $ajax_added_textfield_enabled_when_checkbox_trigger_checked is now
    // present in the dom.
    $ajax_added_textfield_enabled_when_checkbox_trigger_checked = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertNotEmpty($ajax_added_textfield_enabled_when_checkbox_trigger_checked);
    // The ajax added textfield should be disabled.
    $this->assertTrue($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Change state: check the ajax_added_trigger.
    $ajax_added_trigger->check();
    $this->assertEquals(1, $ajax_added_trigger->getValue());
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    // $textfield_invisible_single_ajax_added is now invisible.
    $this->assertFalse($textfield_invisible_single_ajax_added->isVisible());
    // $textfield_invisible_multiple_ajax_added_and is now invisible.
    $this->assertFalse($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    // $textfield_visible_single_ajax_added is now visible.
    $this->assertTrue($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Change state: select the not_ajax_select_trigger 0 option.
    $not_ajax_select_trigger->setValue(0);
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    // $textfield_visible_not_ajax_single is now invisible.
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    // $textfield_visible_multiple is now invisible.
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertFalse($textfield_invisible_single_ajax_added->isVisible());
    // $textfield_invisible_multiple_ajax_added_and is now visible.
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));

    // Change state: select the ajax_affected_select_trigger 0 option.
    $ajax_affected_select_trigger->setValue(0);
    // $textfield_visible_ajax_single in now invisible.
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertFalse($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($checkbox_trigger->isChecked());
    $this->assertTrue($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));

    // Change state: check the checkbox trigger.
    $checkbox_trigger->check();
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertFalse($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($checkbox_trigger->isChecked());
    $this->assertFalse($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertFalse($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));

    // Change state: check the shared checkbox trigger.
    $shared_checkbox_trigger->check();
    // $shared_checkbox_trigger is now checked.
    $this->assertTrue($shared_checkbox_trigger->isChecked());
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertFalse($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($checkbox_trigger->isChecked());
    $this->assertFalse($ajax_added_textfield_enabled_when_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertFalse($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    // $textfield_enabled_when_shared_checkbox_trigger_checked is now enabled.
    $this->assertFalse($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Change state: set the shared textfield trigger to value1.
    $shared_textfield_trigger->setValue('value1');
    // $textfield_enabled_when_shared_textfield_trigger_value is now enabled.
    $this->assertFalse($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Trigger Ajax reload.
    $ajax_reload->uncheck();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertTrue($shared_checkbox_trigger->isChecked());
    // $ajax_removed_textfield_with_shared_trigger is now in the DOM.
    $ajax_removed_textfield_with_shared_trigger = $page->findField('ajax_removed_textfield_with_shared_trigger');
    $this->assertNotEmpty($ajax_removed_textfield_with_shared_trigger);
    // $ajax_removed_textfield_with_shared_trigger_state is now in the DOM.
    $ajax_removed_textfield_with_shared_trigger_state = $page->findField('ajax_removed_textfield_with_shared_trigger_state');
    $this->assertNotEmpty($ajax_removed_textfield_with_shared_trigger_state);
    // Even though $shared_checkbox_trigger is checked it is still disabled
    // because $ajax_affected_select_trigger is 0.
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger_state->hasAttribute('disabled'));
    // $ajax_added_trigger should no longer be present in the DOM.
    $ajax_added_trigger = $page->findField('ajax_added_trigger');
    $this->assertEmpty($ajax_added_trigger);
    $this->assertEquals(0, $ajax_affected_select_trigger->getValue());
    $this->assertEquals(0, $not_ajax_select_trigger->getValue());
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    // $textfield_invisible_single_ajax_added is now visible.
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    // $textfield_invisible_multiple_ajax_added_or is now visible.
    $this->assertTrue($textfield_invisible_multiple_ajax_added_or->isVisible());
    // $textfield_visible_single_ajax_added in now invisible.
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($checkbox_trigger->isChecked());
    // $ajax_added_textfield_enabled_when_checkbox_trigger_checked is no
    // longer in the dom.
    $ajax_added_textfield_enabled_when_checkbox_trigger_checked = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($ajax_added_textfield_enabled_when_checkbox_trigger_checked);
    $this->assertFalse($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertFalse($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertFalse($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Change state: select the not_ajax_select_trigger 1 option.
    $not_ajax_select_trigger->setValue(1);
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertFalse($textfield_visible_ajax_single->isVisible());
    // $textfield_visible_not_ajax_single is now visible.
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertFalse($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    // $textfield_invisible_multiple_ajax_added_or is now invisible.
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Change state: select the ajax_affected_select_trigger 1 option.
    $ajax_affected_select_trigger->setValue(1);
    // $ajax_removed_textfield_with_shared_trigger_state is now enabled.
    $this->assertFalse($ajax_removed_textfield_with_shared_trigger_state->hasAttribute('disabled'));
    // $ajax_removed_textfield_with_shared_trigger is now enabled.
    $this->assertFalse($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    // $textfield_visible_ajax_single is now visible.
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    // $textfield_visible_multiple is now visible.
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertFalse($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));
    $this->assertFalse($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Change state: uncheck the checkbox trigger.
    $checkbox_trigger->uncheck();
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $ajax_added_textfield_enabled_when_checkbox_trigger_checked = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($ajax_added_textfield_enabled_when_checkbox_trigger_checked);
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));

    // Change state: uncheck the shared checkbox trigger.
    $shared_checkbox_trigger->uncheck();
    // $ajax_removed_textfield_with_shared_trigger is now disabled.
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $ajax_added_textfield_enabled_when_checkbox_trigger_checked = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($ajax_added_textfield_enabled_when_checkbox_trigger_checked);
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    // $textfield_enabled_when_shared_checkbox_trigger_checked is now disabled.
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Change state: unset shared textfield trigger.
    $shared_textfield_trigger->setValue('');
    // $ajax_removed_textfield_with_shared_trigger_state is now disabled.
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger_state->hasAttribute('disabled'));
    // $textfield_enabled_when_shared_textfield_trigger_value is now disabled.
    $this->assertTrue($textfield_enabled_when_shared_textfield_trigger_value->hasAttribute('disabled'));

    // Trigger Shared Ajax & States trigger.
    $shared_ajax_states_trigger->setValue('0');
    $this->assertTrue($ajax_removed_textfield_with_shared_trigger->hasAttribute('disabled'));
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1 = $page->findField('ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1');
    $this->assertNotEmpty($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1);
    $this->assertTrue($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1->hasAttribute('disabled'));
    $this->assertTrue($textfield_enabled_when_shared_checkbox_trigger_checked->hasAttribute('disabled'));

    // Trigger Ajax & Change state: set shared ajax states trigger to 1.
    $shared_ajax_states_trigger->setValue('1');
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $this->assertFalse($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1->hasAttribute('disabled'));

    // Trigger Ajax & Change state: set shared ajax states trigger to ''.
    $shared_ajax_states_trigger->setValue('');
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1 = $page->findField('ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1');
    $this->assertEmpty($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1);

    // Trigger Ajax & Change state: set shared ajax states trigger to 1.
    $shared_ajax_states_trigger->setValue('1');
    $this->assertTrue($textfield_visible_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_not_ajax_single->isVisible());
    $this->assertTrue($textfield_visible_multiple->isVisible());
    $this->assertTrue($textfield_invisible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_invisible_multiple_ajax_added_and->isVisible());
    $this->assertFalse($textfield_invisible_multiple_ajax_added_or->isVisible());
    $this->assertFalse($textfield_visible_single_ajax_added->isVisible());
    $this->assertTrue($textfield_visible_single_ajax_set->isVisible());
    $this->assertTrue($textfield_enabled_when_checkbox_trigger_is_checked->hasAttribute('disabled'));
    $ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1 = $page->findField('ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1');
    $this->assertNotEmpty($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1);
    $this->assertFalse($ajax_added_textfield_enabled_when_shared_ajax_states_trigger_1->hasAttribute('disabled'));

  }

  /**
   * Tests cleanup when Ajax removes a dependent but not its dependee.
   */
  protected function doAjaxDependentCleanupTests(): void {
    $this->drupalGet('form-test/javascript-states-form');
    $page = $this->getSession()->getPage();
    $ajax_reload = $page->findField('ajax_reload');
    $this->assertNotEmpty($ajax_reload);
    $dependent = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($dependent);

    $count_dependents = <<<'JS'
return Drupal.states.processedDependees[':input[name="checkbox_trigger"]'].length;
JS;
    $initial_count = $this->getSession()->evaluateScript($count_dependents);

    $ajax_reload->check();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $dependent = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertNotEmpty($dependent);
    $this->assertEquals($initial_count + 1, $this->getSession()->evaluateScript($count_dependents));

    $ajax_reload->uncheck();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $dependent = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertEmpty($dependent);
    $this->assertEquals($initial_count, $this->getSession()->evaluateScript($count_dependents));

    $ajax_reload->check();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $dependent = $page->findField('ajax_added_textfield_enabled_when_checkbox_trigger_checked');
    $this->assertNotEmpty($dependent);
    $this->assertEquals($initial_count + 1, $this->getSession()->evaluateScript($count_dependents));
  }

}
