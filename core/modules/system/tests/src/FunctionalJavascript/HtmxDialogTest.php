<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the HTMX standard and modal dialog functionality.
 */
#[Group('htmx')]
#[RunTestsInSeparateProcesses]
class HtmxDialogTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'dialog_renderer_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a standard dialog opens as a non-modal dialog.
   */
  public function testStandardDialog(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('normal_dialog');

    // The wrapper uses the default variant class.
    $this->assertJsCondition("document.querySelector('drupal-dialog').classList.contains('drupal_dialog--default')");
    // The dialog closes on any close request, including a backdrop click.
    $this->assertJsCondition("document.querySelector('drupal-dialog dialog').getAttribute('closedby') === 'any'");
    // The dialog has no modal flag.
    $this->assertJsCondition("!document.querySelector('drupal-dialog dialog').hasAttribute('data-modal')");
    // The dialog is opened with show(), not showModal().
    $this->assertJsCondition("!document.querySelector('drupal-dialog dialog').matches(':modal')");
    // The dialog is inserted directly after the main canvas.
    $this->assertJsCondition("document.querySelector('[data-off-canvas-main-canvas]').nextElementSibling.matches('drupal-dialog')");
    $this->assertSession()->elementTextContains('css', 'drupal-dialog .dialog__title', 'Thing 1');
    $this->assertSession()->elementTextContains('css', 'drupal-dialog .dialog__content', 'Look at me in a dialog!');
  }

  /**
   * Tests that a modal dialog opens in the browser's modal layer.
   */
  public function testModalDialog(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('normal_modal');

    // The wrapper uses the modal variant class.
    $this->assertJsCondition("document.querySelector('drupal-dialog').classList.contains('drupal_dialog--modal')");
    // The dialog closes on a close request only, not a backdrop click.
    $this->assertJsCondition("document.querySelector('drupal-dialog dialog').getAttribute('closedby') === 'closerequest'");
    // The dialog has the modal flag.
    $this->assertJsCondition("document.querySelector('drupal-dialog dialog').hasAttribute('data-modal')");
    // The dialog is opened with showModal().
    $this->assertJsCondition("document.querySelector('drupal-dialog dialog').matches(':modal')");
  }

  /**
   * Tests that a form opens in a dialog.
   */
  public function testForm(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('modal_form');
    // Verify the form.
    $this->assertSession()->elementExists('css', '#drupal-dialog-dialog-renderer-test-dot-modal-form');
    $this->assertSession()->elementExists('css', 'drupal-dialog dialog form[data-drupal-selector="dialog-renderer-test-form"]');
    $this->assertSession()->elementExists('css', 'drupal-dialog dialog form[data-drupal-selector="dialog-renderer-test-form"] input[data-drupal-selector="edit-submit-1"]');
     }

  /**
   * Tests that an additional class is added to a modal dialog.
   */
  public function testModalDialogAdditionalClass(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('styled_modal_single_class');

    $this->assertSession()->elementExists('css', 'drupal-dialog dialog.dialog--wide');
  }

  /**
   * Tests that closing a standard dialog removes it from the DOM.
   */
  public function testClosingStandardDialogRemovesElement(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('normal_dialog');

    $this->getSession()->getPage()->find('css', '.dialog__titlebar-close')->click();
    // The dialog element is removed from the DOM.
    $this->assertSession()->waitForElementRemoved('css', 'drupal-dialog-dialog-renderer-test-dot-dialog-content');
  }

  /**
   * Tests that closing a modal dialog removes it from the DOM.
   */
  public function testClosingModalDialogRemovesElement(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('normal_modal');

    $this->getSession()->getPage()->find('css', '.dialog__titlebar-close')->click();
    // The dialog element is removed from the DOM.
    $this->assertSession()->waitForElementRemoved('css', 'drupal-dialog-dialog-renderer-test-dot-dialog-content');
  }

  /**
   * Tests that subsequent HTMX swaps replace the previous dialog.
   */
  public function testHtmxSwapReplacesDialog(): void {
    $this->drupalGet('/htmx-dialog-test-markup');
    $this->openDialog('normal_dialog');

    $this->getCheckbox('normal_dialog')->click();
    // Only one dialog exists after the swap.
    $this->assertJsCondition("document.querySelectorAll('drupal-dialog').length === 1");
  }

  /**
   * Opens a dialog from the test fixture.
   *
   * @param string $name
   *   The name attribute of the checkbox that triggers the dialog.
   */
  private function openDialog(string $name): void {
    $this->getCheckbox($name)->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
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
