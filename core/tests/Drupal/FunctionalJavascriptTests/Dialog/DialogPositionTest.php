<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Dialog;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the JavaScript functionality of the dialog position.
 */
#[Group('dialog')]
#[RunTestsInSeparateProcesses]
class DialogPositionTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests if the dialog UI works properly with block layout page.
   */
  public function testDialogOpenAndClose(): void {
    $admin_user = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/block');
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();

    // Open the dialog using the place block link.
    $placeBlockLink = $page->findLink('Place block');
    $this->assertTrue($placeBlockLink->isVisible(), 'Place block button exists.');
    $placeBlockLink->click();
    $assert_session->assertWaitOnAjaxRequest();
    $dialog = $page->find('css', '.ui-dialog');
    $this->assertTrue($dialog->isVisible(), 'Dialog is opened after clicking the Place block button.');

    // Close the dialog again.
    $closeButton = $page->find('css', '.ui-dialog-titlebar-close');
    $closeButton->click();
    $dialog = $page->find('css', '.ui-dialog');
    $this->assertNull($dialog, 'Dialog is closed after clicking the close button.');

    // Resize the window. The test should pass after waiting for JavaScript to
    // finish as no Javascript errors should have been triggered. If there were
    // javascript errors the test will fail on that.
    $session->resizeWindow(625, 625);
    usleep(5000);
  }

  /**
   * Tests keyboard scrolling after clicking non-interactive dialog content.
   */
  #[DataProvider('keyboardScrollKeys')]
  public function testDialogKeyboardScrolling(string $key): void {
    $this->drupalLogin($this->drupalCreateUser(['administer blocks']));
    $this->drupalGet('admin/structure/block');
    $session = $this->getSession();
    $page = $session->getPage();
    $page->clickLink('Place block');
    $dialog = $this->assertSession()->waitForElementVisible('css', '.ui-dialog-content');
    $this->assertNotNull($dialog);
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertTrue($session->wait(1000, 'document.querySelector(".ui-dialog-content").scrollHeight > document.querySelector(".ui-dialog-content").clientHeight'));
    $dialog->find('css', 'th')->click();

    // Send real browser key events: synthetic events do not perform scrolling.
    $before = $session->evaluateScript('document.querySelector(".ui-dialog-content").scrollTop');
    $session->getDriver()->getWebDriverSession()->postActions([
      'actions' => [[
        'type' => 'key',
        'id' => 'keyboard',
        'actions' => [
          ['type' => 'keyDown', 'value' => $key],
          ['type' => 'keyUp', 'value' => $key],
        ],
      ],
      ],
    ]);
    $this->assertTrue($session->wait(1000, "document.querySelector('.ui-dialog-content').scrollTop > $before"), 'The keyboard scrolls the dialog.');
    $this->assertTrue($session->evaluateScript('document.activeElement === document.querySelector(".ui-dialog-content")'));
  }

  /**
   * Provides keys which scroll dialog content.
   */
  public static function keyboardScrollKeys(): array {
    return [
      'Arrow down' => ["\u{E015}"],
      'Page down' => ["\u{E00F}"],
    ];
  }

}
