<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Opens Views UI configuration links outside the editor's AJAX dialog.
 */
trait NoAjaxFormTestTrait {

  /**
   * Opens a dialog link in a new browser tab or window.
   */
  protected function openDialogLinkInNewWindow(NodeElement $link): void {
    $session = $this->getSession();
    $url = $link->getAttribute('href');
    $session->executeScript('window.open(' . json_encode($url) . ', "views-ui-form")');
    $session->switchToWindow('views-ui-form');
  }

  /**
   * Asserts that a form field is hidden, waiting for JavaScript updates.
   */
  protected function assertFieldHidden(string $locator): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->assertTrue($page->waitFor(10, fn () => !$assert_session->fieldExists($locator)->isVisible()), sprintf('The field "%s" is hidden.', $locator));
  }

  /**
   * Provides dialog and standalone form workflows.
   */
  public static function providerDialogModes(): array {
    return [
      'AJAX dialog' => [FALSE],
      'standalone form' => [TRUE],
    ];
  }

}
