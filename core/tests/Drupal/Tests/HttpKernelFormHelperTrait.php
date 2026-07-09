<?php

declare(strict_types=1);

namespace Drupal\Tests;

/**
 * Provides a method to submit forms using the HTTP kernel to make requests.
 *
 * This is for use by kernel tests, in combination with
 * \Drupal\Tests\HttpKernelUiHelperTrait and its limitations.
 *
 * This is a separate opt-in trait rather than part of
 * \Drupal\Tests\HttpKernelUiHelperTrait, because that trait is used by
 * \Drupal\KernelTests\KernelTestBase, and kernel tests implementing
 * \Drupal\Core\Form\FormInterface declare an incompatible submitForm()
 * method.
 */
trait HttpKernelFormHelperTrait {

  /**
   * Fills and submits a form.
   *
   * @param array $edit
   *   Field data in an associative array. Changes the current input fields
   *   (where possible) to the values indicated.
   *
   *   A checkbox can be set to TRUE to be checked and should be set to FALSE
   *   to be unchecked.
   * @param string $submit
   *   Value of the submit button whose click is to be emulated. For example,
   *   'Save'. The processing of the request depends on this value. For
   *   example, a form may have one button with the value 'Save' and another
   *   button with the value 'Delete', and execute different code depending on
   *   which one is clicked.
   * @param string|null $form_html_id
   *   (optional) HTML ID of the form to be submitted. On some pages there are
   *   many identical forms, so just using the value of the submit button is
   *   not enough. For example: 'trigger-node-presave-assign-form'. Note that
   *   this is not the Drupal $form_id, but rather the HTML ID of the form,
   *   which is typically the same thing but with hyphens replacing the
   *   underscores.
   *
   * @see \Drupal\Tests\UiHelperTrait::submitForm()
   */
  protected function submitForm(array $edit, $submit, $form_html_id = NULL): void {
    $assert_session = $this->assertSession();
    $submit_button = $assert_session->buttonExists($submit);
    // Check if button has a form attribute set.
    if ($form_id = $submit_button->getAttribute('form')) {
      $form = $assert_session->elementExists('xpath', "//form[@id='$form_id']");
      $action = $form->getAttribute('action');
    }
    // Get the form.
    elseif (isset($form_html_id)) {
      $form = $assert_session->elementExists('xpath', "//form[@id='$form_html_id']");
      $submit_button = $assert_session->buttonExists($submit, $form);
      $action = $form->getAttribute('action');
    }
    else {
      $form = $assert_session->elementExists('xpath', './ancestor::form', $submit_button);
      $action = $form->getAttribute('action');
    }

    // Edit the form values.
    foreach ($edit as $name => $value) {
      $field = $assert_session->fieldExists($name, $form);

      $value = match ($field->getAttribute('type')) {
        // Provide support for the values '1' and '0' for checkboxes instead of
        // TRUE and FALSE.
        // @todo Get rid of supporting 1/0 by converting all tests cases using
        // this to boolean values.
        'checkbox' => (bool) $value,
        // Mink only allows strings for text, number and radio button values.
        'text', 'number', 'radio' => (string) $value,
        default => $value,
      };

      $field->setValue($value);
    }

    // Submit form.
    $submit_button->press();

    if ($this->htmlOutputEnabled) {
      $out = $this->getSession()->getPage()->getContent();
      $html_output = 'POST request to: ' . $action .
        '<hr />Ending URL: ' . $this->getSession()->getCurrentUrl();
      $html_output .= '<hr />' . $out;
      $html_output .= $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }

  }

}
