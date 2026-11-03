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
  protected static $modules = ['block', 'ajax_test'];

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

    // Keep a changed pixel width when resizing the browser window.
    $session->executeScript("jQuery('.ui-dialog-content').dialog('option', 'width', 500)");
    foreach ([[1280, 1024], [1024, 768]] as [$width, $height]) {
      $message = "Browser window: {$width} x {$height}";
      $session->resizeWindow($width, $height);
      $this->assertDialogHeightFitsViewport($message);
      $assert_session->assertVisibleInViewport('css', '.ui-dialog-title', FALSE, $message);
      $this->assertStringContainsString('width: 500px;', $dialog->getAttribute('style'), $message);
    }

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
   * Tests that dialog titles are positioned in the viewport on opening.
   */
  #[DataProvider('dialogPositionProvider')]
  public function testDialogPositionOnScrolledPage(bool $modal, int|string $max_height, int $content_height, int|string|null $expected_height): void {
    $position = $this->openPositionDialog(['maxHeight' => $max_height], $modal, $content_height);
    $this->assertGreaterThan(0, $position['scrollY']);
    $this->assertTitleInViewport($position);
    if ($expected_height !== NULL) {
      $height = $expected_height === '95%' ? floor(0.95 * $position['availableHeight']) : $expected_height;
      $this->assertEqualsWithDelta($height, $position['dialog']['height'], 1);
    }
  }

  /**
   * Provides dialog modes, maximum heights, content heights, and expected heights.
   */
  public static function dialogPositionProvider(): array {
    return [
      'modal, short content' => [TRUE, '95%', 100, NULL],
      'modal, percentage' => [TRUE, '95%', 1000, '95%'],
      'modal, pixels' => [TRUE, 400, 1000, 400],
      'non-modal, percentage' => [FALSE, '95%', 1000, '95%'],
      'non-modal, pixels' => [FALSE, 400, 1000, 400],
    ];
  }

  /**
   * Tests positioning options and reopening an existing dialog.
   */
  public function testDialogPositionOptions(): void {
    $session = $this->getSession();
    foreach ([TRUE, FALSE] as $modal) {
      $message = ($modal ? 'Modal' : 'Non-modal') . ' dialog with autoResize disabled';
      $this->assertTitleInViewport($this->openPositionDialog(['autoResize' => FALSE], $modal, $modal ? 40 : NULL), $message);
      $dialog = $this->assertSession()->elementExists('css', '.ui-dialog');
      $this->assertStringContainsString('position: absolute;', $dialog->getAttribute('style'), $message);
      $session->getPage()->pressButton('Close');
    }

    // Enable automatic positioning on a dialog that is already open.
    $this->openPositionDialog(['autoResize' => FALSE]);
    $this->assertTitleInViewport($this->openPositionDialog(['autoResize' => TRUE]), 'Enable autoResize on an open dialog');
    $dialog = $this->assertSession()->elementExists('css', '.ui-dialog');
    $this->assertStringContainsString('position: fixed;', $dialog->getAttribute('style'), 'Enable autoResize on an open dialog');
    $session->getPage()->pressButton('Close');
    foreach ([TRUE, FALSE] as $modal) {
      $message = ($modal ? 'Modal' : 'Non-modal') . ' dialog reopened';
      $this->assertTitleInViewport($this->openPositionDialog([], $modal), $message);
      $session->getPage()->pressButton('Close');
    }

    // Explicit position options remain effective after opening.
    $this->openPositionDialog([], FALSE);
    $this->setDialogPosition(['collision' => 'none']);
    $dialog = $this->assertSession()->elementExists('css', '.ui-dialog');
    $style = $dialog->getAttribute('style');
    preg_match('/\bleft: ([\d.-]+)px;/', $style, $left);
    preg_match('/\btop: ([\d.-]+)px;/', $style, $top);
    $this->assertEqualsWithDelta(40, (float) $left[1], 1, 'Explicit horizontal position');
    $this->assertEqualsWithDelta(60, (float) $top[1], 1, 'Explicit vertical position');

    // Repositioning preserves an explicitly changed positioning mode.
    $this->setDialogPosition(mode: 'absolute');
    $this->assertStringContainsString('position: absolute;', $dialog->getAttribute('style'), 'Preserve the explicit positioning mode');
    $session->getPage()->pressButton('Close');

    // Center the dialog in the area left by Drupal's displacement offsets.
    $offsets = $session->evaluateScript('({...Drupal.displace.offsets})');
    try {
      $this->setDisplaceOffsets(['left' => 100, 'top' => 80]);
      $position = $this->openPositionDialog([], FALSE);
      $rect = $position['dialog'];
      $this->assertEqualsWithDelta(50, $rect['left'] + $rect['width'] / 2 - $position['viewport']['width'] / 2, 1, 'Horizontal displacement offset');
      $this->assertEqualsWithDelta(40, $rect['top'] + $rect['height'] / 2 - $position['viewport']['height'] / 2, 1, 'Vertical displacement offset');
      $this->assertTitleInViewport($position, 'Dialog with displacement offsets');
    }
    finally {
      $session->getPage()->pressButton('Close');
      $this->setDisplaceOffsets($offsets);
    }
  }

  /**
   * Sets Drupal's viewport displacement offsets.
   */
  protected function setDisplaceOffsets(array $offsets): void {
    $offsets += array_fill_keys(['left', 'right', 'top', 'bottom'], 0);
    $offsets = json_encode($offsets, JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript(
      "Object.assign(Drupal.displace.offsets, $offsets)",
    );
  }

  /**
   * Sets the dialog position, optionally changing its positioning mode.
   */
  protected function setDialogPosition(array $position = [], ?string $mode = NULL): void {
    $position += [
      'my' => 'left top',
      'at' => 'left+40 top+60',
    ];
    $position = json_encode($position, JSON_THROW_ON_ERROR);
    $mode = json_encode($mode, JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript(<<<JS
      const dialog = jQuery('#dialog-test-content');
      if ($mode !== null) {
        dialog.dialog('widget')[0].style.position = $mode;
      }
      dialog.dialog('option', 'position', {...$position, of: window});
      JS);
  }

  /**
   * Opens a dialog and captures its position before any timer can correct it.
   *
   * A content height loads a fresh page and scrolls it; NULL reuses the existing
   * dialog content.
   */
  protected function openPositionDialog(array $options, bool $modal = TRUE, ?int $content_height = NULL): array {
    if ($content_height !== NULL) {
      $this->drupalGet('ajax-test/dialog-sizes', ['query' => ['content' => 1, 'height' => $content_height]]);
      $this->getSession()->executeScript("document.body.style.minHeight = '2000px'; window.scrollTo(0, 1000)");
    }
    $options = json_encode($options + ['title' => 'Dialog title', 'maxHeight' => 400], JSON_THROW_ON_ERROR);
    $method = $modal ? 'showModal' : 'show';
    return $this->getSession()->evaluateScript(<<<JS
      (() => {
        const content = document.getElementById('dialog-test-content');
        const dialog = Drupal.dialog(content, {
          ...$options,
          close: () => dialog.close(),
        });
        dialog.$method();
        const widget = jQuery(content).dialog('widget')[0];
        return {
          scrollY: window.scrollY,
          title: widget.querySelector('.ui-dialog-title').getBoundingClientRect().toJSON(),
          dialog: widget.getBoundingClientRect().toJSON(),
          viewport: {width: jQuery(window).width(), height: jQuery(window).height()},
          availableHeight: jQuery(window).height() - Drupal.displace.offsets.top - Drupal.displace.offsets.bottom,
        };
      })()
      JS);
  }

  /**
   * Asserts that the captured title lies within the viewport.
   */
  protected function assertTitleInViewport(array $position, string $message = 'Dialog title in viewport'): void {
    $this->assertGreaterThanOrEqual(0, $position['title']['top'], "$message: title above viewport");
    $this->assertLessThanOrEqual($position['viewport']['height'], $position['title']['bottom'], "$message: title below viewport");
  }

  /**
   * Waits until the dialog fits its content or the available viewport height.
   */
  protected function assertDialogHeightFitsViewport(string $message): void {
    $this->assertJsCondition(<<<'JS'
      (() => {
        const content = jQuery('.ui-dialog-content');
        const widget = content.parent();
        const availableHeight = jQuery(window).height() - Drupal.displace.offsets.top - Drupal.displace.offsets.bottom;
        const contentHeight = content[0].scrollHeight + widget.outerHeight() - content.innerHeight();
        return Math.abs(widget.outerHeight() - Math.min(Math.trunc(0.95 * availableHeight), contentHeight)) < 1;
      })()
      JS, message: $message);
  }

}
