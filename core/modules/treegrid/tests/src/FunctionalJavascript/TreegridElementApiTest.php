<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_test_ui\Controller\TreegridTestUiController;
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

  /**
   * A unit can open a row and refresh a level without waiting for itself.
   */
  public function testUnitLoadsAndRefreshesInline(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      return element.exclusive(async (unit) => [
        await unit.expandRow(row),
        await unit.refreshLevel('p1'),
      ]);
    JS);

    $this->assertSame([TRUE, TRUE], $this->getSession()->evaluateScript('return window.apiTest.result;'));
    $this->assertCount(2, $this->getSession()->getPage()->findAll('css', 'tr[role="row"][data-parent-id="p1"]'));
  }

  /**
   * An expand started behind a running unit loads only after it, as waiting.
   */
  public function testQueuedExpandWaitsBehindRunningUnit(): void {
    $this->openPage();
    $this->waitForReady();
    $this->countChildrenRequests();
    $this->getSession()->executeScript(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      element.exclusive(() => new Promise((resolve) => window.setTimeout(resolve, 1000)));
      const expanded = element.expandRow(row);
      window.apiTest.waiting = {
        loading: row.dataset.treegridLoading,
        busy: row.getAttribute('aria-busy'),
        expanded: row.getAttribute('aria-expanded'),
        requests: window.apiTest.requests,
      };
      expanded.then((value) => {
        window.apiTest.result = value;
        window.apiTest.done = true;
      });
    JS);

    $waiting = $this->getSession()->evaluateScript('return window.apiTest.waiting;');
    $this->assertSame('waiting', $waiting['loading']);
    $this->assertSame('true', $waiting['busy']);
    $this->assertSame('false', $waiting['expanded']);
    $this->assertSame(0, $waiting['requests']);

    $this->assertJsCondition('window.apiTest.done === true');
    $this->assertTrue($this->getSession()->evaluateScript('return window.apiTest.result;'));
    $this->assertSame(1, $this->getSession()->evaluateScript('return window.apiTest.requests;'));
    $row = $this->getSession()->getPage()->find('css', 'tr[data-id="p1"]');
    $this->assertSame('true', $row->getAttribute('aria-expanded'));
    $this->assertNull($row->getAttribute('data-treegrid-loading'));
    $this->assertNull($row->getAttribute('aria-busy'));
  }

  /**
   * A second expand while a load waits or runs returns the same promise.
   */
  public function testSecondExpandReturnsSamePromise(): void {
    \Drupal::state()->set(TreegridTestUiController::CHILDREN_DELAY_KEY, 600);
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      const first = element.expandRow(row);
      const whileWaiting = element.expandRow(row);
      await new Promise((resolve) => window.setTimeout(resolve, 300));
      const whileRunning = element.expandRow(row);
      const results = [first === whileWaiting, first === whileRunning];
      await first;
      return results;
    JS);

    $this->assertSame([TRUE, TRUE], $this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * Collapsing a row whose load waits cancels the wait.
   */
  public function testCollapseCancelsWaitingLoad(): void {
    $this->assertWaitCanceledBy("element.collapseRow(row);");
  }

  /**
   * Pressing the toggle of a row whose load waits cancels the wait.
   */
  public function testToggleCancelsWaitingLoad(): void {
    $this->assertWaitCanceledBy("row.querySelector('[data-treegrid-toggle]').click();");
  }

  /**
   * Pressing Left on a row whose load waits cancels the wait.
   */
  public function testLeftKeyCancelsWaitingLoad(): void {
    $this->assertWaitCanceledBy(
      "row.focus(); row.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true, cancelable: true }));",
    );
  }

  /**
   * A descendant marked keep-visible blocks the collapse.
   */
  public function testCollapseRowBlockedByKeepVisibleDescendant(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      await element.expandRow(row);
      const child = element.tree.querySelector('tr[data-id="c1a"]');
      child.dataset.treegridKeepVisible = 'true';
      const blocked = element.collapseRow(row);
      const stillOpen = row.getAttribute('aria-expanded');
      delete child.dataset.treegridKeepVisible;
      return [blocked, stillOpen, element.collapseRow(row)];
    JS);

    $this->assertSame([FALSE, 'true', TRUE], $this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * A refresh picks up a change made to the server's tree.
   */
  public function testRefreshLevelPicksUpServerChange(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      await element.expandRow(element.tree.querySelector('tr[data-id="p1"]'));
      return true;
    JS);
    $tree = TreegridTestUiController::CHILDREN;
    $tree['p1'] = ['c1b', 'c1c'];
    \Drupal::state()->set(TreegridTestUiController::TREE_KEY, $tree);
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const refreshed = await element.refreshLevel('p1');
      const ids = Array.from(element.tree.querySelectorAll('tr[data-parent-id="p1"]')).map((row) => row.dataset.id);
      return { refreshed, ids };
    JS);

    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertTrue($result['refreshed']);
    $this->assertSame(['c1b', 'c1c'], $result['ids']);
  }

  /**
   * Expanding a parent fires one level-loaded event with its id.
   */
  public function testLevelLoadedFiresOncePerExpand(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const loaded = [];
      element.addEventListener('treegrid:level-loaded', (event) => loaded.push(event.detail.parentId));
      await element.expandRow(element.tree.querySelector('tr[data-id="p1"]'));
      return loaded;
    JS);

    $this->assertSame(['p1'], $this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * A failing children request fires level-load-failed and leaves the row shut.
   */
  public function testFailedLoadFiresLevelLoadFailed(): void {
    \Drupal::state()->set(TreegridTestUiController::CHILDREN_FAIL_KEY, 1);
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      const failures = [];
      const loaded = [];
      element.addEventListener('treegrid:level-load-failed', (event) => failures.push(event.detail));
      element.addEventListener('treegrid:level-loaded', (event) => loaded.push(event.detail));
      const opened = await element.expandRow(row);
      return {
        opened,
        failures,
        loaded,
        expanded: row.getAttribute('aria-expanded'),
        children: element.tree.querySelectorAll('tr[data-parent-id="p1"]').length,
      };
    JS);

    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertFalse($result['opened']);
    $this->assertEquals([['parentId' => 'p1', 'status' => 500]], $result['failures']);
    $this->assertSame([], $result['loaded']);
    $this->assertSame('false', $result['expanded']);
    $this->assertSame(0, $result['children']);
  }

  /**
   * The row-change event fires before any row is touched, with its change kind.
   */
  public function testBeforeRowsChangeFiresFirst(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const seen = [];
      element.addEventListener('treegrid:before-rows-change', (event) => {
        seen.push({
          change: event.detail.change,
          parentId: event.detail.parentId,
          rows: element.tree.querySelectorAll('tr[data-id]').length,
          hasRemoved: !!element.tree.querySelector('tr[data-id="c1a"]'),
        });
      });
      await element.expandRow(element.tree.querySelector('tr[data-id="p1"]'));
      return seen;
    JS);
    $inserted = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertEquals([['change' => 'insert', 'parentId' => 'p1', 'rows' => 3, 'hasRemoved' => FALSE]], $inserted);

    $tree = TreegridTestUiController::CHILDREN;
    $tree['p1'] = ['c1b'];
    \Drupal::state()->set(TreegridTestUiController::TREE_KEY, $tree);
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const seen = [];
      element.addEventListener('treegrid:before-rows-change', (event) => {
        seen.push({
          change: event.detail.change,
          rows: element.tree.querySelectorAll('tr[data-id]').length,
          hasRemoved: !!element.tree.querySelector('tr[data-id="c1a"]'),
        });
      });
      await element.refreshLevel('p1');
      return {
        seen,
        rowsAfter: element.tree.querySelectorAll('tr[data-id]').length,
      };
    JS);
    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertEquals([['change' => 'reconcile', 'rows' => 5, 'hasRemoved' => TRUE]], $result['seen']);
    $this->assertSame(4, $result['rowsAfter']);
  }

  /**
   * A refresh of a level that was never opened only updates the parent row.
   */
  public function testRefreshOfUnloadedLevelOnlyUpdatesHasChildren(): void {
    $this->openPage();
    $this->waitForReady();
    $tree = TreegridTestUiController::CHILDREN;
    $tree['p2'] = [];
    $tree['leaf1'] = ['x1'];
    \Drupal::state()->set(TreegridTestUiController::TREE_KEY, $tree);
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const state = (id) => {
        const row = element.tree.querySelector('tr[data-id="' + id + '"]');
        return {
          hasChildren: row.dataset.hasChildren,
          loaded: row.dataset.treegridLoaded ?? null,
          children: element.tree.querySelectorAll('tr[data-parent-id="' + id + '"]').length,
        };
      };
      await element.refreshLevel('p2');
      await element.refreshLevel('leaf1');
      return { p2: state('p2'), leaf1: state('leaf1') };
    JS);

    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertEquals(['hasChildren' => 'false', 'loaded' => NULL, 'children' => 0], $result['p2']);
    $this->assertEquals(['hasChildren' => 'true', 'loaded' => NULL, 'children' => 0], $result['leaf1']);
  }

  /**
   * A refresh of a collapsed loaded level inserts its new rows hidden.
   */
  public function testRefreshOfCollapsedLevelInsertsRowsHidden(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      await element.expandRow(row);
      element.collapseRow(row);
      return true;
    JS);
    $tree = TreegridTestUiController::CHILDREN;
    $tree['p1'] = ['c1a', 'c1b', 'c1c'];
    \Drupal::state()->set(TreegridTestUiController::TREE_KEY, $tree);
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      await element.refreshLevel('p1');
      const added = element.tree.querySelector('tr[data-id="c1c"]');
      return added ? added.dataset.treegridHidden : null;
    JS);

    $this->assertSame('true', $this->getSession()->evaluateScript('return window.apiTest.result;'));
  }

  /**
   * Clearing a row's children empties its level, and a later expand loads it again.
   */
  public function testClearChildrenForgetsLoadedLevel(): void {
    $this->openPage();
    $this->waitForReady();
    $this->countChildrenRequests();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      await element.expandRow(row);
      const afterFirst = window.apiTest.requests;
      element.clearChildren(row);
      const cleared = {
        children: element.tree.querySelectorAll('tr[data-parent-id="p1"]').length,
        loaded: row.dataset.treegridLoaded ?? null,
      };
      await element.expandRow(row);
      return {
        afterFirst,
        cleared,
        afterSecond: window.apiTest.requests,
        children: element.tree.querySelectorAll('tr[data-parent-id="p1"]').length,
      };
    JS);

    $result = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertSame(1, $result['afterFirst']);
    $this->assertEquals(['children' => 0, 'loaded' => NULL], $result['cleared']);
    $this->assertSame(2, $result['afterSecond']);
    $this->assertSame(2, $result['children']);
  }

  /**
   * Rows a refresh drops and rows a clear removes have their behaviors detached.
   */
  public function testRemovedRowsDetachTheirBehaviors(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      await element.expandRow(row);
      return window.treegridTestUiDetaches;
    JS);
    $before = $this->getSession()->evaluateScript('return window.apiTest.result;');

    $tree = TreegridTestUiController::CHILDREN;
    $tree['p1'] = ['c1b'];
    \Drupal::state()->set(TreegridTestUiController::TREE_KEY, $tree);
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      await element.refreshLevel('p1');
      return window.treegridTestUiDetaches;
    JS);
    $afterRefresh = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertSame($before + 1, $afterRefresh);

    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      element.clearChildren(element.tree.querySelector('tr[data-id="p1"]'));
      return window.treegridTestUiDetaches;
    JS);
    $afterClear = $this->getSession()->evaluateScript('return window.apiTest.result;');
    $this->assertGreaterThan($afterRefresh, $afterClear);
  }

  /**
   * Opening a leaf marks its level loaded and resolves false.
   */
  public function testExpandRowOnLeafResolvesFalse(): void {
    $this->openPage();
    $this->waitForReady();
    $this->runAsync(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="leaf1"]');
      return {
        opened: await element.expandRow(row),
        loaded: row.dataset.treegridLoaded ?? null,
        expanded: row.getAttribute('aria-expanded'),
      };
    JS);

    $this->assertEquals(
      ['opened' => FALSE, 'loaded' => 'true', 'expanded' => NULL],
      $this->getSession()->evaluateScript('return window.apiTest.result;'),
    );
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
   * Counts the children requests the page sends in window.apiTest.requests.
   */
  private function countChildrenRequests(): void {
    $this->getSession()->executeScript(<<<'JS'
      window.apiTest = { requests: 0, done: false, result: null };
      const originalFetch = window.fetch;
      window.fetch = function (url) {
        if (String(url).includes('/treegrid-test-ui/children')) {
          window.apiTest.requests += 1;
        }
        return originalFetch.apply(this, arguments);
      };
    JS);
  }

  /**
   * Asserts an action cancels an expand waiting behind a running unit.
   *
   * @param string $action
   *   Script that cancels the wait; `element` and `row` (p1) are in scope.
   */
  private function assertWaitCanceledBy(string $action): void {
    $this->openPage();
    $this->waitForReady();
    $this->countChildrenRequests();
    $this->getSession()->executeScript(<<<JS
      const element = document.querySelector('drupal-treegrid');
      const row = element.tree.querySelector('tr[data-id="p1"]');
      element.exclusive(() => new Promise((resolve) => window.setTimeout(resolve, 800)));
      const expanded = element.expandRow(row);
      $action
      expanded.then((value) => {
        window.apiTest.result = value;
        window.apiTest.done = true;
      });
    JS);

    $this->assertJsCondition('window.apiTest.done === true');
    $this->assertFalse($this->getSession()->evaluateScript('return window.apiTest.result;'));
    $row = $this->getSession()->getPage()->find('css', 'tr[data-id="p1"]');
    $this->assertNull($row->getAttribute('data-treegrid-loading'));
    // Wait past the blocking unit; nothing may be fetched after it.
    usleep(1200000);
    $this->assertSame(0, $this->getSession()->evaluateScript('return window.apiTest.requests;'));
  }

  /**
   * Runs a script that returns a promise and stores its value in window.apiTest.
   *
   * @param string $script
   *   The script body; it must return a promise.
   */
  private function runAsync(string $script): void {
    $this->getSession()->executeScript(
      'window.apiTest = Object.assign(window.apiTest || {}, { done: false, result: null });'
      . '(async function () {' . $script . '})().then(function (value) {'
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
