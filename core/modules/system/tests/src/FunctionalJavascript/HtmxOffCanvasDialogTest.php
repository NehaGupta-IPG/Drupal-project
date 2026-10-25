<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the HTMX off-canvas dialog functionality.
 */
#[Group('htmx')]
#[RunTestsInSeparateProcesses]
class HtmxOffCanvasDialogTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'dialog_renderer_test', 'language'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a side dialog displaces the main canvas in LTR.
   */
  public function testSidePositionDisplacesLtrMainCanvas(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openOffCanvas('side_off_canvas');

    // The side dialog is inserted directly after the main canvas.
    $this->assertJsCondition("document.querySelector('[data-off-canvas-main-canvas]').nextElementSibling.matches('drupal-dialog')");
    // The main canvas right padding matches the dialog width within 1px.
    $this->assertJsCondition("Math.abs(parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight) - document.querySelector('.dialog--position-side').getBoundingClientRect().width) < 1");
    // Drupal.displace() sets a positive right offset variable.
    $this->assertJsCondition("parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--drupal-displace-offset-right')) > 0");
  }

  /**
   * Tests that a side dialog displaces the main canvas in RTL.
   */
  public function testSidePositionDisplacesRtlMainCanvas(): void {
    ConfigurableLanguage::createFromLangcode('ar')->save();
    \Drupal::configFactory()->getEditable('language.negotiation')
      ->set('url.prefixes', ['en' => '', 'ar' => 'ar'])
      ->save();

    $this->drupalGet('/ar/htmx-dialog-test-markup');
    $this->openOffCanvas('side_off_canvas');

    // The page is rendered right-to-left.
    $this->assertJsCondition("document.documentElement.dir === 'rtl'");
    // The main canvas left padding matches the dialog width within 1px.
    $this->assertJsCondition("Math.abs(parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingLeft) - document.querySelector('.dialog--position-side').getBoundingClientRect().width) < 1");
    $this->assertSame(0.0, (float) $this->getSession()->evaluateScript("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight)"));
    // Drupal.displace() sets a positive left offset variable.
    $this->assertJsCondition("parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--drupal-displace-offset-left')) > 0");
  }

  /**
   * Tests that a top dialog pushes the main canvas down in normal flow.
   */
  public function testTopPositionUsesNaturalFlow(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $canvasTop = (float) $this->getSession()->evaluateScript("document.querySelector('[data-off-canvas-main-canvas]').getBoundingClientRect().top");
    $this->openOffCanvas('top_off_canvas');

    // The top dialog is inserted directly before the main canvas.
    $this->assertJsCondition("document.querySelector('[data-off-canvas-main-canvas]').previousElementSibling.matches('drupal-dialog')");
    // The main canvas starts at or below the bottom edge of the dialog.
    $this->assertJsCondition("document.querySelector('[data-off-canvas-main-canvas]').getBoundingClientRect().top >= document.querySelector('.dialog--position-top').getBoundingClientRect().bottom");
    $this->assertSame(0.0, (float) $this->getSession()->evaluateScript("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight)"));

    $this->getSession()->getPage()->find('css', '.dialog__titlebar-close')->click();
    // The dialog element is removed from the DOM.
    $this->assertJsCondition("document.querySelector('drupal-dialog') === null");
    $this->assertSame($canvasTop, (float) $this->getSession()->evaluateScript("document.querySelector('[data-off-canvas-main-canvas]').getBoundingClientRect().top"));
  }

  /**
   * Tests that a side dialog overlays the main canvas at a narrow width.
   */
  public function testNarrowSidePositionOverlaysCanvas(): void {
    $this->getSession()->resizeWindow(600, 768);
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openOffCanvas('side_off_canvas');
    // The side dialog width matches the viewport width within 1px.
    $this->assertJsCondition("Math.abs(document.querySelector('.dialog--position-side').getBoundingClientRect().width - window.innerWidth) < 1");
    $this->assertSame(0.0, (float) $this->getSession()->evaluateScript("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight)"));
  }

  /**
   * Tests that closing a side dialog removes displacement and body state.
   */
  public function testClosingSideDialogRestoresMainCanvas(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openOffCanvas('side_off_canvas');
    // The main canvas has right padding while the dialog is open.
    $this->assertJsCondition("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight) > 0");

    $this->getSession()->getPage()->find('css', '.dialog__titlebar-close')->click();
    // The dialog element is removed from the DOM.
    $this->assertSession()->waitForElementRemoved('css', 'drupal-dialog-dialog-renderer-test-dot-dialog-content');
    // The main canvas right padding returns to 0.
    $this->assertJsCondition("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight) === 0");
    $this->assertFalse((bool) $this->getSession()->evaluateScript("document.body.classList.contains('js-off-canvas-dialog-open')"));
  }

  /**
   * Tests that subsequent HTMX swaps replace the previous dialog.
   */
  public function testHtmxSwapReplacesOffCanvasDialog(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openOffCanvas('side_off_canvas');
    $this->getCheckbox('side_off_canvas')->click();
    // Only one dialog exists after the swap.
    $this->assertJsCondition("document.querySelectorAll('drupal-dialog').length === 1");
    $this->getCheckbox('side_off_canvas')->click();
    // Only one dialog exists after the swap.
    $this->assertJsCondition("document.querySelectorAll('drupal-dialog').length === 1");
    $this->getCheckbox('top_off_canvas')->click();

    // The top dialog replaces the side dialog.
    $this->assertJsCondition("document.querySelectorAll('drupal-dialog').length === 1");
    // The remaining dialog uses the top position.
    $this->assertJsCondition("document.querySelector('.dialog--position-top') !== null");
    // The main canvas has no right padding with a top dialog.
    $this->assertJsCondition("parseFloat(getComputedStyle(document.querySelector('[data-off-canvas-main-canvas]')).paddingRight) === 0");
  }

  /**
   * Opens an off-canvas dialog from the test fixture.
   *
   * @param string $name
   *   The name attribute of the checkbox that triggers the dialog.
   */
  private function openOffCanvas(string $name): void {
    $this->getCheckbox($name)->click();
    $this->assertSession()->waitForElementVisible('css', 'drupal-dialog #drupal-off-canvas-wrapper');
  }

  /**
   * Gets a fixture checkbox by its name attribute.
   *
   * @param string $name
   *   The name attribute of the checkbox.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The checkbox element.
   */
  private function getCheckbox(string $name): NodeElement {
    return $this->assertSession()->elementExists('css', "input[name=\"$name\"]");
  }

}
