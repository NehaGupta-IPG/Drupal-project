<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that pointer-events are restored after AJAX-triggered element replacement.
 *
 * When an AJAX response replaces markup in the DOM (e.g., file upload widget),
 * the pointer-event lock mechanism must properly clean up the newly-rendered
 * elements rather than operating on stale (detached) DOM references.
 */
#[Group('Ajax')]
#[RunTestsInSeparateProcesses]
class AjaxPointerEventsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests submit button is clickable after AJAX replaces its container.
   *
   * This simulates the file upload widget scenario: the AJAX callback
   * replaces the wrapper via replaceWith, detaching the original submit
   * button. Without a fix, the new submit button retains pointer-events: none.
   */
  public function testPointerEventsRestoredAfterAjaxReplacement(): void {
    $this->drupalGet('/ajax-test/pointer-events-form');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert = $this->assertSession();

    // Verify the submit button is initially clickable (no pointer-events: none).
    $submit = $assert->elementExists('css', '[data-drupal-selector="edit-submit"]');
    $this->assertNotEquals('none', $submit->getAttribute('style') ?: '', 'Submit button should not have pointer-events: none initially.');

    // Trigger AJAX replacement by changing the select value.
    $select = $page->findField('select_trigger');
    $this->assertNotNull($select, 'Select trigger field exists.');
    $select->selectOption('option_a');

    // Wait for the AJAX request to complete.
    $assert->assertWaitOnAjaxRequest();

    // After AJAX replacement, the submit button must not have
    // pointer-events: none. The old elements have been detached and the
    // unlock mechanism should clean up the new elements.
    $newSubmit = $assert->elementExists('css', '[data-drupal-selector="edit-submit"]');

    // Check that pointer-events is not 'none' on the submit button.
    $pointerEvents = $session->evaluateScript(
      "window.getComputedStyle(document.querySelector('[data-drupal-selector=\"edit-submit\"]')).pointerEvents"
    );
    $this->assertNotEquals('none', $pointerEvents, 'Submit button must not have pointer-events: none after AJAX replacement.');

    // Also check inline style directly.
    $inlineStyle = $session->evaluateScript(
      "document.querySelector('[data-drupal-selector=\"edit-submit\"]').style.pointerEvents"
    );
    $this->assertNotEquals('none', $inlineStyle, 'Submit button must not have inline pointer-events: none after AJAX replacement.');

    // Verify the submit button is actually clickable and submits the form.
    $newSubmit->click();
    $assert->statusMessageContainsAfterWait('Form submitted successfully.', 'status');
  }

  /**
   * Tests that pointer-events are restored after multiple AJAX replacements.
   */
  public function testPointerEventsRestoredAfterMultipleReplacements(): void {
    $this->drupalGet('/ajax-test/pointer-events-form');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert = $this->assertSession();

    // First AJAX replacement.
    $select = $page->findField('select_trigger');
    $select->selectOption('option_a');
    $assert->assertWaitOnAjaxRequest();

    // Second AJAX replacement.
    $select = $page->findField('select_trigger');
    $select->selectOption('option_b');
    $assert->assertWaitOnAjaxRequest();

    // Submit button must still be clickable.
    $pointerEvents = $session->evaluateScript(
      "window.getComputedStyle(document.querySelector('[data-drupal-selector=\"edit-submit\"]')).pointerEvents"
    );
    $this->assertNotEquals('none', $pointerEvents, 'Submit button must not have pointer-events: none after multiple AJAX replacements.');

    // Verify the button actually works.
    $submit = $assert->elementExists('css', '[data-drupal-selector="edit-submit"]');
    $submit->click();
    $assert->statusMessageContainsAfterWait('Form submitted successfully.', 'status');
  }

}
