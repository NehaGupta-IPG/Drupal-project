<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\system\Functional\Form\StubForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests form element validation mechanisms that do not require HTTP requests.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class FormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file'];

  /**
   * Check several empty values for required forms elements.
   *
   * Carriage returns, tabs, spaces, and unchecked checkbox elements are not
   * valid content for a required field.
   *
   * If the form field is found in $form_state->getErrors() then the test pass.
   */
  public function testRequiredFields(): void {
    // Originates from https://www.drupal.org/node/117748.
    // Sets of empty strings and arrays.
    $empty_strings = [
      '""' => "",
      '"\n"' => "\n",
      '" "' => " ",
      '"\t"' => "\t",
      '" \n\t "' => " \n\t ",
      '"\n\n\n\n\n"' => "\n\n\n\n\n",
    ];
    $empty_arrays = ['array()' => []];
    $empty_checkbox = [NULL];

    $elements['textfield']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'textfield'];
    $elements['textfield']['empty_values'] = $empty_strings;

    $elements['telephone']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'tel'];
    $elements['telephone']['empty_values'] = $empty_strings;

    $elements['url']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'url'];
    $elements['url']['empty_values'] = $empty_strings;

    $elements['search']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'search'];
    $elements['search']['empty_values'] = $empty_strings;

    $elements['password']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'password'];
    $elements['password']['empty_values'] = $empty_strings;

    $elements['password_confirm']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'password_confirm'];
    // Provide empty values for both password fields.
    foreach ($empty_strings as $key => $value) {
      $elements['password_confirm']['empty_values'][$key] = ['pass1' => $value, 'pass2' => $value];
    }

    $elements['textarea']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'textarea'];
    $elements['textarea']['empty_values'] = $empty_strings;

    $elements['radios']['element'] = [
      '#title' => $this->randomMachineName(),
      '#type' => 'radios',
      '#options' => [
        '' => 'None',
        $this->randomMachineName(),
        $this->randomMachineName(),
        $this->randomMachineName(),
      ],
    ];
    $elements['radios']['empty_values'] = $empty_arrays;

    $elements['checkbox']['element'] = [
      '#title' => $this->randomMachineName(),
      '#type' => 'checkbox',
      '#required' => TRUE,
    ];
    $elements['checkbox']['empty_values'] = $empty_checkbox;

    $elements['checkboxes']['element'] = [
      '#title' => $this->randomMachineName(),
      '#type' => 'checkboxes',
      '#options' => [
        $this->randomMachineName(),
        $this->randomMachineName(),
        $this->randomMachineName(),
      ],
    ];
    $elements['checkboxes']['empty_values'] = $empty_arrays;

    $elements['select']['element'] = [
      '#title' => $this->randomMachineName(),
      '#type' => 'select',
      '#options' => [
        '' => 'None',
        $this->randomMachineName(),
        $this->randomMachineName(),
        $this->randomMachineName(),
      ],
    ];
    $elements['select']['empty_values'] = $empty_strings;

    $elements['file']['element'] = ['#title' => $this->randomMachineName(), '#type' => 'file'];
    $elements['file']['empty_values'] = $empty_strings;

    // Go through all the elements and all the empty values for them.
    foreach ($elements as $type => $data) {
      foreach ($data['empty_values'] as $key => $empty) {
        foreach ([TRUE, FALSE] as $required) {
          $form_id = $this->randomMachineName();
          $form = [];
          $form_state = new FormState();
          $form['op'] = ['#type' => 'submit', '#value' => 'Submit'];
          $element = $data['element']['#title'];
          $form[$element] = $data['element'];
          $form[$element]['#required'] = $required;
          $user_input[$element] = $empty;
          $user_input['form_id'] = $form_id;
          $form_state->setUserInput($user_input);
          $form_state->setFormObject(new StubForm($form_id, $form));
          $form_state->setMethod('POST');
          // The form token CSRF protection should not interfere with this test,
          // so we bypass it by setting the token to FALSE.
          $form['#token'] = FALSE;
          \Drupal::formBuilder()->prepareForm($form_id, $form, $form_state);
          \Drupal::formBuilder()->processForm($form_id, $form, $form_state);
          $errors = $form_state->getErrors();
          if ($required) {
            // Make sure we have a form error for this element.
            $this->assertTrue(isset($errors[$element]), "Check empty($key) '$type' field '$element'");
          }
          elseif ($type == 'select') {
            // Select elements are going to have validation errors with empty
            // input, since those are not allowed choices. Just make sure the
            // error is not "field is required".
            $this->assertTrue((empty($errors[$element]) || !str_contains('field is required', (string) $errors[$element])), "Optional '$type' field '$element' is not treated as a required element");
          }
          else {
            // Make sure there is *no* form error for this element. We're
            // not using assertEmpty() because the array key might not exist.
            $this->assertArrayNotHasKey($element, $errors, "Optional '$type' field '$element' should have no errors with empty input.");
          }
        }
      }
    }
  }

}
