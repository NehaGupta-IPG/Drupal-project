<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_form_errors\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Validation test for inline_form_errors.
 *
 * @group inline_form_errors
 * @phpstan-ignore testClass.missingAttribute.Group, testClass.missingAttribute.RunInSeparateProcesses
 */
class InlineErrorFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'inline_form_errors',
    'form_test',
  ];

  /**
   * Tests validation for required checkbox, select, and radio elements.
   *
   * Submits a test form containing several types of form elements. The form
   * is submitted twice, first without values for required fields and then
   * with values. Each submission is checked for relevant error messages.
   *
   * @see \Drupal\form_test\Form\FormTestValidateRequiredForm
   */
  public function testRequiredCheckboxesRadio(): void {
    $form = \Drupal::formBuilder()->getForm('\Drupal\form_test\Form\FormTestValidateRequiredForm');

    // Attempt to submit the form with no required fields set.
    $edit = [];
    $this->drupalGet('form-test/validate-required');
    $this->submitForm($edit, 'Submit');
    $session = $this->assertSession();

    // The only error messages that should appear are the relevant 'required'
    // messages for each field.
    $expected = [];
    foreach (['textfield', 'checkboxes', 'select', 'radios'] as $key) {
      if (isset($form[$key]['#required_error'])) {
        $expected[] = $form[$key]['#required_error'];
      }
      elseif (isset($form[$key]['#form_test_required_error'])) {
        $expected[] = $form[$key]['#form_test_required_error'];
      }
      else {
        $expected[] = $form[$key]['#title'] . ' field is required.';
      }
      // Ensure errors are wrapped with an ID that can be referenced by aria-errormessages
      $error_id = $form[$key]['#id'] . '--error-message';
      $session->elementExists('css', '#' . $error_id);
      // Make sure form element references error.
      $type = match ($form[$key]['#type']) {
        'textfield' => 'input',
        'checkboxes' => 'checkbox',
        'select' => 'select',
        'radios' => 'radio',
      };
      $session->elementExists('css', $type . '[aria-errormessage=' . $error_id . ']');
    }

    // Check the page for error messages.
    $errors = $this->xpath('//div[contains(@class, "error")]//li');
    foreach ($errors as $error) {
      $this->assertContains($error->getText(), array_map(fn($n) => (string) $n, $expected));
      $expected_key = array_search($error->getText(), $expected);
      unset($expected[$expected_key]);
    }

    // Fail if any expected messages were not found.
    $this->assertEmpty($expected, 'Found unexpected error messages');

    // Verify that input elements are still empty.
    $session->fieldValueEquals('textfield', '');
    $session->checkboxNotChecked('edit-checkboxes-foo');
    $session->checkboxNotChecked('edit-checkboxes-bar');
    $this->assertTrue($session->optionExists('edit-select', '')->isSelected());
    $session->checkboxNotChecked('edit-radios-foo');
    $session->checkboxNotChecked('edit-radios-bar');
    $session->checkboxNotChecked('edit-radios-optional-foo');
    $session->checkboxNotChecked('edit-radios-optional-bar');
    $session->checkboxNotChecked('edit-radios-optional-default-value-false-foo');
    $session->checkboxNotChecked('edit-radios-optional-default-value-false-bar');

    // Submit again with required fields set and verify that there are no
    // error messages.
    $edit = [
      'textfield' => $this->randomString(),
      'checkboxes[foo]' => TRUE,
      'select' => 'foo',
      'radios' => 'bar',
    ];
    $this->submitForm($edit, 'Submit');
    // Verify that no error message is displayed when all required fields are
    // filled.
    $this->assertSession()->elementNotExists('xpath', '//div[contains(@class, "error")]');
    $this->assertSession()->pageTextContains("The form_test_validate_required_form form was submitted successfully.");
  }

}
