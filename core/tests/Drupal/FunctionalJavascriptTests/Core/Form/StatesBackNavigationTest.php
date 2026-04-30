<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Core\Form;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests that #states visibility is correct after browser back-navigation.
 *
 * When a user changes a form element that controls #states visibility,
 * navigates to another page, and then presses the browser back button, the
 * browser restores form element values but does NOT fire change events. This
 * causes Drupal's states.js to display stale visibility because it only
 * evaluates conditions on initial page load and in response to change events.
 *
 * @group javascript
 *
 * @see https://www.drupal.org/project/drupal/issues/3587011
 */
class StatesBackNavigationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests #states visibility after simulated browser back-navigation.
   *
   * When a user navigates back, the browser performs two actions:
   * 1. Restores form element values from session history (without firing
   *    change events).
   * 2. Fires the "pageshow" event.
   *
   * After this sequence, #states visibility should match the restored values.
   * Currently, states.js only evaluates on initial page load and in response
   * to change events, so restored values are not reflected.
   */
  public function testStatesVisibilityAfterBackNavigation(): void {
    $this->drupalGet('/form-test/javascript-states-form');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // The "Item visible when select trigger has value2" should be hidden
    // initially because select_trigger defaults to empty ("- None -").
    $item = $page->find('css', '#edit-item-visible-when-select-trigger-has-value2');
    $this->assertNotNull($item);
    $this->assertFalse($item->isVisible(), 'Item is hidden when select trigger has no value.');

    // Simulate what Chrome does on back-navigation: restore the form value
    // without firing a change event, then fire the pageshow event.
    $this->simulateBrowserBackNavigation();

    // After the browser's back-navigation sequence, #states visibility
    // should reflect the restored form values.
    $assert->waitForElementVisible('css', '#edit-item-visible-when-select-trigger-has-value2');
    $this->assertTrue(
      $item->isVisible(),
      'Item should be visible when select trigger is restored to "value2" after back-navigation.'
    );
  }

  /**
   * Simulates browser back-navigation form restoration behavior.
   *
   * When a browser navigates back without using bfcache, it:
   * 1. Restores form element values from session history without firing
   *    change events.
   * 2. Fires the "pageshow" event with persisted=true.
   *
   * This simulation is necessary because headless Chrome uses a synthetic
   * bfcache that restores the entire page state (including JavaScript and
   * DOM) on back-navigation. This masks the bug entirely — states.js never
   * needs to re-evaluate because the pre-navigation state is perfectly
   * preserved. In real-world usage, browsers frequently skip bfcache (due to
   * Cache-Control headers, unload listeners, etc.) and instead perform a
   * fresh page load while restoring form values silently. This method
   * replicates that real-world sequence.
   */
  private function simulateBrowserBackNavigation(): void {
    // Step 1: Browser restores the form value without firing a change event.
    $this->getSession()->executeScript(
      "document.querySelector('#edit-select-trigger').value = 'value2';"
    );

    // Step 2: Browser fires the pageshow event.
    $this->getSession()->executeScript(
      "window.dispatchEvent(new PageTransitionEvent('pageshow', {persisted: true}));"
    );
  }

}
