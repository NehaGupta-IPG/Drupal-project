<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests scrollTop command for regular pages and dialog windows.
 *
 * @group Ajax
 */
class AjaxScrollTopCommandTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test', 'ajax_forms_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The current session.
   */
  protected $session;

  /**
   * The current assert session.
   */
  protected $assert;

  /**
   * The amount of pixels that scrollTop adds to the scroll element.
   */
  protected $scrollTopOffset;

  /**
   * The amount of pixels for the scroll offset.
   */
  protected $scrollOffset;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->session = $this->getSession();
    $this->assert = $this->assertSession();

    // Making window smaller to make scroll active.
    $this->session->resizeWindow(800, 250);

    // This offset comes from the js function
    // Drupal.AjaxCommands.prototype.scrollTop().
    $this->scrollTopOffset = 10;

    // The amount of pixels to scroll for moving target out of the visible area.
    $this->scrollOffset = 600;
  }

  /**
   * Tests the scrollTop command.
   */
  public function testAjaxScrollTopCommand() {
    // Test on a regular page.
    $this->drupalGet('/ajax-test/scroll-top-test-action-page');
    $this->executeScrollTopAndTest('document');

    // Test inside an Off Canvas dialog.
    $this->drupalGet('/ajax-test/scroll-top-test-page');
    $this->clickLink('Open ScrollTop form in an off canvas dialog');
    $this->assert->assertWaitOnAjaxRequest();
    $this->assert->waitForElementVisible('css', '#drupal-off-canvas-wrapper');
    $this->executeScrollTopAndTest('"#drupal-off-canvas-wrapper"');

    // Test inside a Modal dialog.
    $this->drupalGet('/ajax-test/scroll-top-test-page');
    $this->clickLink('Open ScrollTop form in a modal dialog');
    $this->assert->assertWaitOnAjaxRequest();
    $this->assert->waitForElementVisible('css', 'div.ui-dialog-content');
    $this->executeScrollTopAndTest('"div.ui-dialog-content"');
  }

  /**
   * A helper function to test the scrollTop scrolling result position.
   */
  private function executeScrollTopAndTest(string $wrapper_selector) {
    // Making scrolls on the parent elements.
    $this->session->executeScript("window.jQuery(document).scrollTop($this->scrollOffset);");
    $this->session->executeScript("window.jQuery($wrapper_selector).scrollTop($this->scrollOffset);");
    $this->clickLink('Execute scrollTop');
    $this->assert->assertWaitOnAjaxRequest();

    $document_scroll = $this->session->evaluateScript("window.jQuery($wrapper_selector).scrollTop();");
    $off_canvas_content_scroll = $this->session
      ->evaluateScript("window.jQuery($wrapper_selector).scrollTop();");
    if ($wrapper_selector == 'document') {
      $off_canvas_content_top_relative = 0;
    }
    else {
      $off_canvas_content_top = $this->session->evaluateScript("window.jQuery($wrapper_selector).offset().top;");
      $off_canvas_content_top_relative = $off_canvas_content_top - $off_canvas_content_scroll;
    }
    $scrolling_element_top = $this->session->evaluateScript("window.jQuery(\"#scroll-top-scroll-target\").offset().top;");
    $scrolling_element_top_relative = $scrolling_element_top - $document_scroll;

    $this->assertEquals(
      floor($scrolling_element_top_relative - $off_canvas_content_top_relative),
      $this->scrollTopOffset,
      'Scrolled position does not match the target element position.'
    );
  }

}
