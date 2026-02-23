<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Various tests of AJAX behavior.
 */
#[Group('Ajax')]
#[RunTestsInSeparateProcesses]
class ElementValidationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test', 'ajax_forms_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tries to post an Ajax change to a form that has a validated element.
   *
   * Drupal AJAX commands update the DOM echoing back the validated values in
   * the form of messages that appear on the page.
   */
  public function testAjaxElementValidation(): void {
    $this->drupalGet('ajax_validation_test');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Partially complete the form with a string.
    $page->fillField('driver_text', 'some dumb text');
    // Move focus away from this field to trigger AJAX.
    $page->findField('spare_required_field')->focus();

    // When the AJAX command updates the DOM a <ul> unsorted list
    // "message__list" structure will appear on the page echoing back the
    // "some dumb text" message.
    $placeholder_text = $assert->waitForElement('css', "[aria-label='Status message'] > ul > li > em:contains('some dumb text')");
    $this->assertNotNull($placeholder_text, 'A callback successfully echoed back a string.');

    $this->drupalGet('ajax_validation_test');
    // Partially complete the form with a number.
    $page->fillField('driver_number', '12345');
    $page->findField('spare_required_field')->focus();

    // The AJAX request/response will complete successfully when an
    // InsertCommand injects a message with a placeholder element into the DOM
    // with the submitted number.
    $placeholder_number = $assert->waitForElement('css', "[aria-label='Status message'] > ul > li > em:contains('12345')");
    $this->assertNotNull($placeholder_number, 'A callback successfully echoed back a number.');
  }

  /**
   * Tests that suppress_required_fields_validation skips required errors.
   *
   * When an AJAX element has suppress_required_fields_validation set to TRUE,
   * the required field validation should be suppressed during the AJAX request,
   * but full validation should still apply on normal form submission.
   */
  public function testAjaxSuppressRequiredFieldsValidation(): void {
    $this->drupalGet('ajax_validation_test');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Click the AJAX button that has suppress_required_fields_validation.
    // The required field is empty, but required validation should be
    // suppressed.
    $page->pressButton('Suppress validation');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify the AJAX callback was invoked despite the empty required field.
    $assert->pageTextContains('suppress_validation_callback invoked');

    // Verify no required field validation error appeared.
    $assert->pageTextNotContains('Spare Required Field field is required.');

    // Now submit the full form with the required field still empty.
    // Required validation should apply on normal form submission.
    $this->submitForm([], 'Submit');
    $assert->pageTextContains('Spare Required Field field is required.');
  }

}
