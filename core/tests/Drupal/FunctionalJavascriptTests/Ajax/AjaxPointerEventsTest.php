<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\TestFileCreationTrait;
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

  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test', 'file'];

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

  /**
   * Tests that focusKey contains a numeric instanceIndex after initialization.
   *
   * Before the fix, focusKey was computed at bind-time when instanceIndex
   * was still `false`, resulting in all AJAX instances sharing the key
   * "pe:false:focus". After the fix, focusKey is computed lazily inside the
   * focusin handler when instanceIndex is properly assigned.
   */
  public function testFocusKeyUsesNumericInstanceIndex(): void {
    $this->drupalGet('/ajax-test/pointer-events-form');
    $session = $this->getSession();
    $page = $session->getPage();

    // Focus the select element to trigger the focusin handler.
    $select = $page->findField('select_trigger');
    $this->assertNotNull($select, 'Select trigger field exists.');
    $select->focus();

    // Give the focusin handler a moment to execute.
    $this->assertNotEmpty($session->evaluateScript(
      "return Drupal.ajax.instances.filter(function(inst) { return inst && inst._focusPeKey; }).length"
    ), 'At least one AJAX instance should have _focusPeKey set after focus.');

    // Verify the focusKey contains a numeric instanceIndex, not "false".
    $hasStaleKey = $session->evaluateScript(
      "return Drupal.ajax.instances.some(function(inst) { return inst && inst._focusPeKey && inst._focusPeKey.indexOf('false') !== -1; })"
    );
    $this->assertFalse($hasStaleKey, 'No AJAX instance should have "false" in its focusKey.');

    // Verify at least one instance has a properly numeric focusKey.
    $hasNumericKey = $session->evaluateScript(
      "return Drupal.ajax.instances.some(function(inst) { return inst && inst._focusPeKey && /^pe:\\d+:focus$/.test(inst._focusPeKey); })"
    );
    $this->assertTrue($hasNumericKey, 'At least one AJAX instance should have a numeric focusKey like "pe:N:focus".');
  }

  /**
   * Tests submit button remains clickable after a file upload via managed_file.
   *
   * This reproduces the real-world bug: Drupal.file.triggerUploadButton fires
   * a programmatic mousedown on the hidden upload button, then the AJAX
   * response replaces the file widget wrapper. Without the fix, the submit
   * button gets a permanent "pe:false:focus" lock from the stale focusKey.
   */
  public function testFileUploadDoesNotLockSubmitButton(): void {
    $this->drupalGet('/ajax-test/file-upload-pointer-events-form');
    $session = $this->getSession();
    $page = $session->getPage();
    $assert = $this->assertSession();

    // Verify submit button is initially clickable.
    $submit = $assert->elementExists('css', '[data-drupal-selector="edit-submit"]');
    $pointerEvents = $session->evaluateScript(
      "document.querySelector('[data-drupal-selector=\"edit-submit\"]').style.pointerEvents"
    );
    $this->assertNotEquals('none', $pointerEvents, 'Submit button should not have pointer-events: none initially.');

    // Upload a file. This triggers Drupal.file.triggerUploadButton which
    // programmatically fires mousedown on the upload button, causing
    // the AJAX flow that exposed the original bug.
    $testFile = $this->getTestFiles('text')[0];
    $fileInput = $page->find('css', 'input[type="file"]');
    $this->assertNotNull($fileInput, 'File input field exists.');
    $fileInput->attachFile(\Drupal::service('file_system')->realpath($testFile->uri));

    // Wait for AJAX upload to complete.
    $assert->assertWaitOnAjaxRequest();

    // After the file upload AJAX replacement, the submit button must NOT
    // have pointer-events: none.
    $pointerEventsAfter = $session->evaluateScript(
      "document.querySelector('[data-drupal-selector=\"edit-submit\"]').style.pointerEvents"
    );
    $this->assertNotEquals('none', $pointerEventsAfter, 'Submit button must not have pointer-events: none after file upload.');

    // Verify no orphaned "pe:false:focus" locks exist on the submit button.
    $locks = $session->evaluateScript(
      "(function() { var d = jQuery('[data-drupal-selector=\"edit-submit\"]').data('drupalPeLocks'); return d ? JSON.stringify(d) : 'none'; })()"
    );
    if ($locks !== 'none') {
      $this->assertStringNotContainsString('false', $locks, 'Submit button should not have any lock keys containing "false".');
    }

    // Verify the submit button is actually clickable.
    $submit = $assert->elementExists('css', '[data-drupal-selector="edit-submit"]');
    $submit->click();
    $assert->statusMessageContainsAfterWait('File form submitted successfully.', 'status');
  }

}
