<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the public API of the <drupal-treegrid> element.
 *
 * Covers the ready signal, handler removal, the lifecycle events and the
 * queue, against the treegrid_test_ui consumer.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridElementApiTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_test_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The element is ready, and says so, only once its provider has registered.
   */
  public function testReadyWaitsForProvider(): void {
    $this->openPage(['delay_provider' => 3000]);
    $this->getSession()->executeScript(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      window.apiTest = { ready: false, attributeAtReady: null };
      element.ready.then(() => {
        window.apiTest.ready = true;
        window.apiTest.attributeAtReady = element.hasAttribute('data-treegrid-ready');
      });
    JS);
    $this->assertFalse($this->getSession()->evaluateScript("return window.apiTest.ready;"));
    $this->assertFalse($this->getSession()->evaluateScript("return document.querySelector('drupal-treegrid').hasAttribute('data-treegrid-ready');"));

    $this->assertJsCondition('window.apiTest.ready === true', 10000);
    $this->assertTrue($this->getSession()->evaluateScript("return window.apiTest.attributeAtReady;"));
  }

  /**
   * A removed click handler stops running while row focus still moves.
   */
  public function testRemovedClickHandlerDoesNotRun(): void {
    $this->openPage();
    $this->getSession()->executeScript(<<<'JS'
      window.apiTest = { clicks: 0 };
      const element = document.querySelector('drupal-treegrid');
      window.apiTest.remove = element.addClickHandler(() => {
        window.apiTest.clicks += 1;
        return false;
      });
    JS);

    $this->markupCell('p2')->click();
    $this->assertSame(1, $this->getSession()->evaluateScript('return window.apiTest.clicks;'));
    $this->assertSame('p2', $this->activeRowId());

    $this->getSession()->executeScript('window.apiTest.remove();');
    $this->markupCell('p1')->click();
    $this->assertSame(1, $this->getSession()->evaluateScript('return window.apiTest.clicks;'));
    $this->assertSame('p1', $this->activeRowId());
  }

  /**
   * The element reports connected, then disconnected, to its own listeners.
   */
  public function testConnectedThenDisconnectedEvents(): void {
    $this->openPage(['delay_provider' => 1500]);
    $this->recordLifecycleEvents();
    $this->waitForReady();
    $this->getSession()->executeScript("document.querySelector('drupal-treegrid').remove();");

    $events = $this->getSession()->evaluateScript('return window.apiTest.events;');
    $this->assertSame(['connected:false', 'disconnected'], $events);
  }

  /**
   * Moving the element in the DOM fires a connected event marked reconnected.
   */
  public function testDomMoveFiresReconnected(): void {
    $this->openPage();
    $this->waitForReady();
    $this->recordLifecycleEvents();
    $this->getSession()->executeScript(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const wrapper = document.createElement('div');
      element.parentNode.appendChild(wrapper);
      wrapper.appendChild(element);
    JS);

    $events = $this->getSession()->evaluateScript('return window.apiTest.events;');
    $this->assertSame(['disconnected', 'connected:true'], $events);
    // The moved tree still navigates.
    $this->markupCell('p2')->click();
    $this->assertSame('p2', $this->activeRowId());
  }

  /**
   * A consumer's setup is skipped for a tree whose behaviors were detached.
   */
  public function testConsumerSetupSkippedAfterDetach(): void {
    $this->openPage(['delay_provider' => 2000]);
    $this->getSession()->executeScript("Drupal.detachBehaviors(document, drupalSettings, 'unload');");
    $this->waitForReady();
    // Give a wrongly continuing setup the chance to run.
    usleep(300000);
    $this->assertNull($this->getSession()->getPage()->find('css', 'drupal-treegrid[data-test-setup]'));
  }

  /**
   * A consumer's setup runs for a tree left alone.
   */
  public function testConsumerSetupRunsWhenReady(): void {
    $this->openPage(['delay_provider' => 1000]);
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-test-setup]'));
  }

  /**
   * Units started together run in order and never overlap.
   */
  public function testUnitsRunInOrderWithoutOverlap(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const log = [];
      const unit = (name, ms) => element.exclusive(async () => {
        log.push('start:' + name);
        await new Promise((resolve) => window.setTimeout(resolve, ms));
        log.push('end:' + name);
        return name;
      });
      return Promise.all([unit('a', 200), unit('b', 20), unit('c', 0)]).then((results) => ({ log, results }));
    JS);

    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertSame(['start:a', 'end:a', 'start:b', 'end:b', 'start:c', 'end:c'], $result['log']);
    $this->assertSame(['a', 'b', 'c'], $result['results']);
  }

  /**
   * A unit that throws does not stop the next one.
   */
  public function testThrowingUnitDoesNotStopTheQueue(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const first = element.exclusive(() => {
        throw new Error('boom');
      }).catch((error) => error.message);
      const second = element.exclusive(async () => 'ok');
      return Promise.all([first, second]);
    JS);

    $this->assertSame(['boom', 'ok'], $this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * A unit's own exclusive() runs at once instead of queueing behind it.
   */
  public function testUnitExclusiveRunsAtOnce(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      return element.exclusive((unit) => unit.exclusive((inner) => inner === unit));
    JS);

    $this->assertTrue($this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * A unit method called after its unit has settled throws.
   */
  public function testUnitMethodAfterSettleThrows(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      let saved;
      return element.exclusive((unit) => {
        saved = unit;
      }).then(() => {
        try {
          saved.exclusive(() => 1);
          return false;
        } catch (error) {
          return true;
        }
      });
    JS);

    $this->assertTrue($this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  // Helpers.

  /**
   * Opens the test page and waits for the element to be in the DOM.
   *
   * @param array<string, int|string> $query
   *   Optional query parameters.
   */
  private function openPage(array $query = []): void {
    $this->drupalGet('/treegrid-test-ui', ['query' => $query]);
    $this->assertSession()->waitForElement('css', 'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]');
  }

  /**
   * Waits until the element carries its ready attribute.
   */
  private function waitForReady(): void {
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-treegrid-ready]'));
  }

  /**
   * Runs a script that returns a promise and stores its value in window.apiTest.
   *
   * @param string $script
   *   The script body; it must return a promise.
   */
  private function runAsync(string $script): void {
    $this->getSession()->executeScript(
      'window.apiTest = { done: false, result: null };'
      . '(function () {' . $script . '})().then(function (value) {'
      . ' window.apiTest.result = value; window.apiTest.done = true; });',
    );
    $this->assertJsCondition('window.apiTest.done === true');
  }

  /**
   * Starts recording the element's lifecycle events on window.apiTest.events.
   */
  private function recordLifecycleEvents(): void {
    $this->getSession()->executeScript(<<<'JS'
      window.apiTest = { events: [] };
      const element = document.querySelector('drupal-treegrid');
      element.addEventListener('treegrid:connected', (event) => {
        window.apiTest.events.push('connected:' + event.detail.reconnected);
      });
      element.addEventListener('treegrid:disconnected', () => {
        window.apiTest.events.push('disconnected');
      });
    JS);
  }

  /**
   * The markup cell of a row, a plain place to click.
   *
   * @param string $id
   *   The row ID.
   */
  private function markupCell(string $id): NodeElement {
    $cell = $this->getSession()->getPage()->find('css', sprintf('tr[role="row"][data-id="%s"] .treegrid-cell-markup', $id));
    $this->assertNotNull($cell, "Row $id should render a markup cell.");
    return $cell;
  }

  /**
   * The data-id of the focused row, or NULL.
   */
  private function activeRowId(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.activeElement && document.activeElement.matches('tr[role=\"row\"]')"
      . ' ? document.activeElement.dataset.id : null;',
    );
  }

}
