<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_reorder_test\Controller\TreegridReorderTestController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests moves as units in the element's queue, and their events.
 *
 * Every move, from any gesture, runs as one unit and fires either
 * treegrid-reorder:moved or treegrid-reorder:failed on the element. The tests
 * record both in window.queueTest.events.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
final class ReorderMoveQueueTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_reorder',
    'treegrid_reorder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser());
    $this->openPage();
  }

  /**
   * Each accepted move fires one moved event, with the right ids.
   */
  public function testOneMovedEventPerAcceptedMove(): void {
    $this->drag('3', '1', 0.1);
    $this->waitForEvents(1);
    $moved = $this->events()[0];
    $this->assertSame('treegrid-reorder:moved', $moved['type']);
    $this->assertSame('3', $moved['detail']['id']);
    $this->assertSame('', $moved['detail']['parentId']);
    $this->assertSame('1', $moved['detail']['beforeId']);
    $this->assertSame('', $moved['detail']['previousParentId']);
    $this->assertSame('drag', $moved['detail']['gesture']);
    $this->assertTrue($moved['detail']['refreshed']);
    $this->assertTrue($moved['detail']['result']['ok']);

    // Rows are now 3, 1, 2, 4. Keyboard move mode: 4 to before 3.
    $this->pressKey('4', ' ');
    $this->stepToDrop('4', 'before:3', 'ArrowUp');
    $this->pressKey('4', 'Enter');
    $this->waitForEvents(2);
    $moved = $this->events()[1];
    $this->assertSame('treegrid-reorder:moved', $moved['type']);
    $this->assertSame('4', $moved['detail']['id']);
    $this->assertSame('3', $moved['detail']['beforeId']);
    $this->assertSame('move-mode', $moved['detail']['gesture']);

    // Shift+Arrow: 2 down past 1... rows are 4, 3, 1, 2; 4 down past 3.
    $this->pressKey('4', 'ArrowDown', TRUE);
    $this->waitForEvents(3);
    $moved = $this->events()[2];
    $this->assertSame('treegrid-reorder:moved', $moved['type']);
    $this->assertSame('4', $moved['detail']['id']);
    $this->assertSame('1', $moved['detail']['beforeId']);
    $this->assertSame('shift-arrow', $moved['detail']['gesture']);

    $this->assertCount(3, $this->events());
  }

  /**
   * A move that changes nothing fires no event.
   */
  public function testNoOpMoveFiresNothing(): void {
    $this->pressKey('1', ' ');
    $this->pressKey('1', 'Enter');
    usleep(600000);

    $this->assertSame([], $this->events());
  }

  /**
   * A refused move fires failed with the server's status and code.
   */
  public function testRefusedMoveFiresFailedWithStatusAndCode(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_FAIL, ['code' => 'nope', 'status' => 400]);

    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->waitForEvents(1);

    $failed = $this->events()[0];
    $this->assertSame('treegrid-reorder:failed', $failed['type']);
    $this->assertSame(400, $failed['detail']['status']);
    $this->assertSame('nope', $failed['detail']['code']);
    $this->assertTrue($failed['detail']['sent']);
    $this->assertSame('1', $failed['detail']['id']);
    $this->assertSame('shift-arrow', $failed['detail']['gesture']);
    $this->assertSame(['1', '2', '3', '4'], $this->topLevelOrder(), 'The swap is put back.');
  }

  /**
   * A server message with an escaped ampersand is announced once-escaped.
   */
  public function testShiftArrowFailureMessageIsAnnouncedOnceEscaped(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_FAIL, ['message' => 'Tom &amp; Jerry']);

    $this->pressKey('1', 'ArrowDown', TRUE);

    $this->assertJsCondition("document.getElementById('drupal-live-announce').textContent.includes('Tom & Jerry')");
  }

  /**
   * A drag failure announces the server message once-escaped.
   */
  public function testDragFailureMessageIsAnnouncedOnceEscaped(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_FAIL, ['message' => 'Tom &amp; Jerry']);

    $this->drag('3', '1', 0.1);

    $this->assertJsCondition("document.getElementById('drupal-live-announce').textContent.includes('Tom & Jerry')");
  }

  /**
   * A request that never gets an answer fires failed with status 0.
   */
  public function testAbortedRequestFiresFailedWithStatusZero(): void {
    $this->getSession()->executeScript(<<<'JS'
      const originalFetch = window.fetch;
      window.fetch = function (url) {
        if (String(url).includes('/treegrid-reorder-test/move')) {
          return Promise.reject(new TypeError('Aborted by the test'));
        }
        return originalFetch.apply(this, arguments);
      };
    JS);

    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->waitForEvents(1);

    $failed = $this->events()[0];
    $this->assertSame('treegrid-reorder:failed', $failed['type']);
    $this->assertSame(0, $failed['detail']['status']);
    $this->assertTrue($failed['detail']['sent']);
  }

  /**
   * A stale response gives the code stale and a failed move.
   */
  public function testStaleMoveFiresFailedWithStaleCode(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_FAIL, ['code' => 'stale', 'status' => 409]);

    $this->drag('3', '1', 0.1);
    // The helper puts the tree into the needs-reload state before the move
    // reports its failure.
    $this->waitForEvents(2);

    $types = array_column($this->events(), 'type');
    $this->assertSame(['treegrid-reorder:needs-reload', 'treegrid-reorder:failed'], $types);
    $failed = $this->events()[1];
    $this->assertSame('stale', $failed['detail']['code']);
    $this->assertSame('drag', $failed['detail']['gesture']);
  }

  /**
   * An expand queued before a drop into the same row loads its level once.
   */
  public function testExpandThenDropIntoSameParentLoadsOnce(): void {
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_DELAY, 300);
    $this->recordRequests();
    $this->startSlowUnit(1500);
    $this->getSession()->executeScript(<<<'JS'
      document.querySelector('drupal-treegrid').expandRow(
        document.querySelector('tr[data-id="3"]'),
      );
    JS);
    $this->drag('1', '3', 0.5);

    $this->assertFirstChildAfterQueuedMove();
  }

  /**
   * A drop queued before an expand of the same row loads its level once.
   */
  public function testDropThenExpandSameParentLoadsOnce(): void {
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_DELAY, 300);
    $this->recordRequests();
    $this->startSlowUnit(1500);
    $this->drag('1', '3', 0.5);
    $this->getSession()->executeScript(<<<'JS'
      document.querySelector('drupal-treegrid').expandRow(
        document.querySelector('tr[data-id="3"]'),
      );
    JS);

    $this->assertFirstChildAfterQueuedMove();
  }

  /**
   * The element is flagged as moving from acceptance until the unit ends.
   */
  public function testMovingFlagCoversTheRequest(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_DELAY, 800);

    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->assertJsCondition("!!document.querySelector('drupal-treegrid[data-treegrid-reorder-moving]')", 2000);
    $this->assertJsCondition("!document.querySelector('drupal-treegrid[data-treegrid-reorder-moving]')", 15000);
    $this->assertCount(1, $this->events());
  }

  /**
   * A second Shift+Arrow during a move is refused, announced once and unsent.
   */
  public function testSecondShiftArrowIsRefused(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_DELAY, 800);
    $this->recordRequests();
    $this->countAssertiveAnnouncements();

    $this->pressKey('1', 'ArrowDown', TRUE);
    // Holding the key repeats it on whatever row has focus.
    for ($i = 0; $i < 5; $i++) {
      $this->pressKey('3', 'ArrowDown', TRUE);
    }
    $this->waitForEvents(6);
    $this->assertJsCondition("!document.querySelector('drupal-treegrid[data-treegrid-reorder-moving]')", 15000);

    $moves = array_filter(
      $this->getSession()->evaluateScript('return window.queueTest.requests;'),
      static fn (string $request): bool => $request === 'move',
    );
    $this->assertCount(1, $moves, 'Only the first move is sent.');
    $this->assertSame(['2', '1', '3', '4'], $this->topLevelOrder());
    $this->assertSame(1, $this->getSession()->evaluateScript('return window.queueTest.assertive;'));
    $codes = array_column(array_column($this->events(), 'detail'), 'code');
    $this->assertCount(5, array_filter($codes, static fn ($code): bool => $code === 'refused'));
  }

  /**
   * Space on another row and a drag during a move do nothing.
   */
  public function testPickUpAndDragAreRefusedDuringMove(): void {
    \Drupal::state()->set(TreegridReorderTestController::MOVE_DELAY, 800);
    $this->recordRequests();

    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->pressKey('4', ' ');
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "return document.querySelectorAll('tr[aria-selected], [data-treegrid-reorder-grabbed]').length;",
    ));
    $this->drag('4', '2', 0.1);
    $this->assertJsCondition("!document.querySelector('drupal-treegrid[data-treegrid-reorder-moving]')", 15000);

    $moves = array_filter(
      $this->getSession()->evaluateScript('return window.queueTest.requests;'),
      static fn (string $request): bool => $request === 'move',
    );
    $this->assertCount(1, $moves);
    $this->assertSame(['2', '1', '3', '4'], $this->topLevelOrder());
  }

  /**
   * A Shift+Arrow row removed while its unit waits fails as unavailable.
   */
  public function testShiftArrowRowRemovedWhileWaitingIsUnavailable(): void {
    $this->startSlowUnit(1200);

    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->getSession()->executeScript(<<<'JS'
      document.querySelector('tr[data-id="1"]').remove();
      document.querySelector('[data-test-field="outside"]').focus();
    JS);
    $this->waitForEvents(1);

    $failed = $this->events()[0];
    $this->assertSame('treegrid-reorder:failed', $failed['type']);
    $this->assertSame('unavailable', $failed['detail']['code']);
    $this->assertSame(0, $failed['detail']['status']);
    $this->assertFalse($failed['detail']['sent']);
    $this->assertSame('outside', $this->getSession()->evaluateScript('return document.activeElement.dataset.testField;'));
  }

  /**
   * A drop whose destination fails to open fails as unavailable, unsent.
   */
  public function testDropIntoDestinationThatFailsToOpenIsUnavailable(): void {
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_HITS, 0);
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_FAIL, ['after' => 0, 'count' => 1]);
    $this->recordRequests();

    $this->drag('1', '3', 0.5);
    $this->waitForEvents(1);

    $failed = $this->events()[0];
    $this->assertSame('treegrid-reorder:failed', $failed['type']);
    $this->assertSame('unavailable', $failed['detail']['code']);
    $this->assertFalse($failed['detail']['sent']);
    $this->assertNotContains('move', $this->getSession()->evaluateScript('return window.queueTest.requests;'));
  }

  /**
   * A failed redraw reports the move, asks for a reload and refuses more moves.
   */
  public function testFailedRedrawEntersNeedsReload(): void {
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_HITS, 0);
    \Drupal::state()->set(TreegridReorderTestController::CHILDREN_FAIL, ['after' => 0, 'count' => NULL]);
    $this->recordRequests();
    $this->getSession()->executeScript('window.pageMarker = "survives";');

    $this->drag('3', '1', 0.1);
    $this->waitForEvents(2);

    $events = $this->events();
    $this->assertSame('treegrid-reorder:moved', $events[0]['type']);
    $this->assertFalse($events[0]['detail']['refreshed']);
    $this->assertSame('treegrid-reorder:needs-reload', $events[1]['type']);
    $this->assertSame('refresh-failed', $events[1]['detail']['reason']);
    $this->assertSame('refresh-failed', $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-needs-reload');",
    ));
    $this->assertSame('survives', $this->getSession()->evaluateScript('return window.pageMarker;'));

    // A second move gesture is refused.
    $this->pressKey('2', 'ArrowDown', TRUE);
    $this->waitForEvents(3);
    $this->assertSame('refused', $this->events()[2]['detail']['code']);

    // Writes through the helper still go out.
    $echo_url = Url::fromRoute('treegrid_reorder_test.echo')->toString();
    $this->getSession()->executeScript(<<<JS
      Drupal.treegridReorder.request(document.querySelector('drupal-treegrid'), '$echo_url', { body: {} })
        .then((result) => { window.queueTest.echoResult = result; });
    JS);
    $this->assertJsCondition('!!window.queueTest.echoResult');
    $this->assertTrue($this->getSession()->evaluateScript('return window.queueTest.echoResult.ok;'));

    // A stale response afterwards makes the state stricter.
    \Drupal::state()->set(TreegridReorderTestController::REVISION, 'theirs');
    $this->getSession()->executeScript(<<<JS
      Drupal.treegridReorder.request(document.querySelector('drupal-treegrid'), '$echo_url', { body: {} });
    JS);
    $this->waitForEvents(4);
    $this->assertSame('treegrid-reorder:needs-reload', $this->events()[3]['type']);
    $this->assertSame('stale', $this->events()[3]['detail']['reason']);
  }

  /**
   * The flash state is present after a Shift+Arrow move, then goes.
   */
  public function testFlashStateIsPresentThenGone(): void {
    $this->pressKey('1', 'ArrowDown', TRUE);
    $this->assertJsCondition("document.querySelector('tr[data-id=\"1\"]').hasAttribute('data-treegrid-reorder-flash')", 3000);
    $this->assertJsCondition("!document.querySelector('tr[data-id=\"1\"]').hasAttribute('data-treegrid-reorder-flash')", 6000);
  }

  // Helpers.

  /**
   * Asserts the level of 3 was loaded once and 1 is now its first child.
   */
  private function assertFirstChildAfterQueuedMove(): void {
    $this->assertJsCondition(
      "!!document.querySelector('tr[data-id=\"1\"][data-parent-id=\"3\"]') && !document.querySelector('drupal-treegrid[data-treegrid-reorder-moving]') && !document.querySelector('[data-treegrid-loading]')",
      20000,
    );
    $requests = $this->getSession()->evaluateScript('return window.queueTest.requests;');
    $move_index = array_search('move', $requests, TRUE);
    $this->assertNotFalse($move_index);
    $loads_before_move = array_filter(
      array_slice($requests, 0, $move_index),
      static fn (string $request): bool => $request === 'children:3',
    );
    $this->assertCount(1, $loads_before_move, 'The level of 3 is loaded once before the move.');

    $ids = $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('tr[role=\"row\"]')).map((row) => row.dataset.id);",
    );
    $this->assertSame(array_values(array_unique($ids)), $ids, 'No duplicate rows.');
    $children = $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('tr[data-parent-id=\"3\"]')).map((row) => row.dataset.id);",
    );
    $this->assertSame('1', $children[0], '1 is the first child of 3.');
  }

  /**
   * Loads the test page, waits for it, and starts recording move events.
   */
  private function openPage(): void {
    $this->drupalGet('/treegrid-reorder-test');
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-test-reorder-wired]'));
    $this->getSession()->executeScript(<<<'JS'
      window.queueTest = { events: [], requests: [] };
      const element = document.querySelector('drupal-treegrid');
      ['treegrid-reorder:moved', 'treegrid-reorder:failed', 'treegrid-reorder:needs-reload'].forEach((type) => {
        element.addEventListener(type, (event) => {
          window.queueTest.events.push({ type, detail: event.detail });
        });
      });
    JS);
  }

  /**
   * Starts recording the children and move requests, in order.
   */
  private function recordRequests(): void {
    $this->getSession()->executeScript(<<<'JS'
      const originalFetch = window.fetch;
      window.fetch = function (url, options) {
        const text = String(url);
        if (text.includes('/treegrid-reorder-test/move')) {
          window.queueTest.requests.push('move');
        }
        else if (text.includes('/treegrid-reorder-test/children')) {
          const parent = new URL(text, window.location.origin).searchParams.get('parent');
          window.queueTest.requests.push('children:' + (parent ?? ''));
        }
        return originalFetch.apply(this, arguments);
      };
    JS);
  }

  /**
   * Counts the assertive Drupal.announce calls in window.queueTest.assertive.
   */
  private function countAssertiveAnnouncements(): void {
    $this->getSession()->executeScript(<<<'JS'
      window.queueTest.assertive = 0;
      const originalAnnounce = Drupal.announce;
      Drupal.announce = function (text, priority) {
        if (priority === 'assertive') {
          window.queueTest.assertive += 1;
        }
        return originalAnnounce.apply(this, arguments);
      };
    JS);
  }

  /**
   * Starts a unit that holds the queue for a while.
   *
   * @param int $milliseconds
   *   How long the unit runs.
   */
  private function startSlowUnit(int $milliseconds): void {
    $this->getSession()->executeScript(sprintf(
      "document.querySelector('drupal-treegrid').exclusive(() => new Promise((resolve) => window.setTimeout(resolve, %d)));",
      $milliseconds,
    ));
  }

  /**
   * The recorded move events.
   *
   * @return array<int, array{type: string, detail: array<string, mixed>}>
   *   The events, in order.
   */
  private function events(): array {
    return $this->getSession()->evaluateScript('return window.queueTest.events;');
  }

  /**
   * Waits until a number of move events have been recorded.
   *
   * @param int $count
   *   The number of events to wait for.
   */
  private function waitForEvents(int $count): void {
    $this->assertJsCondition(sprintf('window.queueTest.events.length >= %d', $count), 15000);
  }

  /**
   * The top-level row order in the DOM.
   *
   * @return string[]
   *   The row IDs.
   */
  private function topLevelOrder(): array {
    return $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('tr[data-parent-id=\"\"]')).map((row) => row.dataset.id);",
    );
  }

  /**
   * Focuses a row and dispatches a keydown on it.
   *
   * @param string $id
   *   The row's data-id.
   * @param string $key
   *   The KeyboardEvent key value.
   * @param bool $shift
   *   Whether Shift is held.
   */
  private function pressKey(string $id, string $key, bool $shift = FALSE): void {
    $key_json = json_encode($key);
    $shift_json = $shift ? 'true' : 'false';
    $this->getSession()->executeScript(<<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      row.tabIndex = 0;
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: $key_json, shiftKey: $shift_json, bubbles: true, cancelable: true,
      }));
    JS);
  }

  /**
   * Steps the move cursor until it reaches a drop position.
   *
   * @param string $id
   *   The grabbed row's data-id.
   * @param string $wanted
   *   The wanted data-treegrid-reorder-drop value, "kind:id".
   * @param string $key
   *   The key that steps the cursor.
   */
  private function stepToDrop(string $id, string $wanted, string $key): void {
    for ($i = 0; $i < 10; $i++) {
      $drop = $this->getSession()->evaluateScript(
        "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-drop');",
      );
      if ($drop === $wanted) {
        return;
      }
      $this->pressKey($id, $key);
    }
    $this->fail("Move cursor never reached '$wanted'.");
  }

  /**
   * Drags one row's handle into a zone of another row.
   *
   * @param string $source_id
   *   The data-id of the row to move.
   * @param string $target_id
   *   The data-id of the row to drop on.
   * @param float $ratio
   *   The vertical position within the target row, 0 (top) to 1 (bottom).
   */
  private function drag(string $source_id, string $target_id, float $ratio): void {
    $this->getSession()->executeScript(<<<JS
      const handle = document.querySelector('tr[data-id="$source_id"] [data-treegrid-reorder-handle]');
      const target = document.querySelector('tr[data-id="$target_id"]');
      target.scrollIntoView({block: 'center'});
      const from = handle.getBoundingClientRect();
      const to = target.getBoundingClientRect();
      const send = (type, x, y) => handle.dispatchEvent(new PointerEvent(type, {
        bubbles: true, cancelable: true, composed: true, pointerId: 1,
        isPrimary: true, button: 0, clientX: x, clientY: y,
      }));
      send('pointerdown', from.left + 2, from.top + 2);
      send('pointermove', from.left + 12, from.top + 12);
      send('pointermove', to.left + 20, to.top + to.height * $ratio);
      send('pointerup', to.left + 20, to.top + to.height * $ratio);
    JS);
  }

}
