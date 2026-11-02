<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\hold_test\HoldTestHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the announcement of AJAX progress to assistive technology.
 */
#[Group('Ajax')]
#[RunTestsInSeparateProcesses]
class ProgressAnnounceTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test', 'hold_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the progress announcement.
   */
  public function testProgressAnnounce(): void {
    $this->drupalGet('ajax-test/progress-announce-form');

    // The default message is announced assertively, then repeated politely.
    $this->pressButtonWithHeldResponse('default', function () {
      $this->assertAnnouncement('Processing...', 'assertive');
      $this->assertAnnouncement("Processing...\u{00A0}", 'polite');
    });
    $this->assertNoAnnouncement();

    // A custom message is announced.
    $this->pressButtonWithHeldResponse('custom_message', function () {
      $this->assertAnnouncement('Hold on');
    });
    $this->assertNoAnnouncement();

    // Progress settings without a message fall back to the default message.
    $this->pressButtonWithHeldResponse('no_message', function () {
      $this->assertAnnouncement('Processing...');
    });
    $this->assertNoAnnouncement();

    // Nothing is announced when the announcement is disabled.
    $this->pressButtonWithHeldResponse('no_announce', function () {
      $this->assertNoAnnouncement();
    });
  }

  /**
   * Presses a button while holding the AJAX response.
   *
   * @param string $button
   *   The button to press.
   * @param callable $assertions
   *   The assertions to run while the response is held.
   */
  protected function pressButtonWithHeldResponse(string $button, callable $assertions): void {
    $this->clearAnnouncement();
    HoldTestHelper::responseHold(TRUE);
    $this->getSession()->getPage()->pressButton($button);
    $assertions();
    HoldTestHelper::responseHold(FALSE);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->clearAnnouncement();
  }

  /**
   * Asserts that a message is announced.
   *
   * @param string $message
   *   The expected message.
   * @param string|null $priority
   *   (optional) The expected aria-live priority of the announcement.
   */
  protected function assertAnnouncement(string $message, ?string $priority = NULL): void {
    $condition = sprintf('document.getElementById("drupal-live-announce")?.textContent === %s', json_encode($message));
    if ($priority) {
      $condition .= sprintf(' && document.getElementById("drupal-live-announce").getAttribute("aria-live") === %s', json_encode($priority));
    }
    $this->assertTrue($this->getSession()->wait(5000, $condition), "Announced: $message");
  }

  /**
   * Asserts that nothing is announced for longer than the repeat interval.
   */
  protected function assertNoAnnouncement(): void {
    $this->assertFalse($this->getSession()->wait(3000, 'document.getElementById("drupal-live-announce")?.textContent'), 'Nothing is announced.');
  }

  /**
   * Clears the live region.
   */
  protected function clearAnnouncement(): void {
    $this->getSession()->executeScript('const element = document.getElementById("drupal-live-announce"); if (element) { element.textContent = ""; }');
  }

}
