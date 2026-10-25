<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_reorder_test\Controller\TreegridReorderTestController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal.treegridReorder.attach() on the reorder test page.
 *
 * The test consumer calls attach() from a behavior and registers a key handler
 * of its own right after it, counting the key events that reach it in
 * window.reorderTestKeyEvents.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
final class ReorderAttachTest extends WebDriverTestBase {

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
  }

  /**
   * A handler registered after attach() on a not-yet-ready tree misses Space.
   */
  public function testHandlersRegisteredAfterAttachRunAfterThem(): void {
    $this->openPage(['delay_provider' => 1500]);
    $this->getSession()->executeScript('window.reorderTestKeyEvents = 0;');

    $this->pressKey('2', ' ');
    $this->assertSame('true', $this->rowAttribute('2', 'aria-selected'));
    $this->assertSame(0, $this->keyEventsSeenByConsumer());

    $this->pressKey('2', 'Escape');
    $this->assertSame(0, $this->keyEventsSeenByConsumer());

    // Outside a move a plain arrow reaches the consumer's handler.
    $this->pressKey('2', 'ArrowDown');
    $this->assertSame(1, $this->keyEventsSeenByConsumer());
  }

  /**
   * During a move a key on the in-row checkbox still drives the move.
   */
  public function testKeyOnInRowControlDrivesMove(): void {
    $this->openPage();
    $this->pressKey('1', ' ');
    $this->assertSame('before:2', $this->dropTarget());

    $this->getSession()->executeScript(<<<'JS'
      const checkbox = document.querySelector('tr[data-id="1"] input[data-treegrid-control]');
      checkbox.dispatchEvent(new KeyboardEvent('keydown', {
        key: 'ArrowDown', bubbles: true, cancelable: true,
      }));
    JS);

    $this->assertSame('after:2', $this->dropTarget());
  }

  /**
   * Outside a move, Space on the in-row checkbox toggles it only.
   */
  public function testSpaceOnInRowControlTogglesIt(): void {
    $this->openPage();
    $this->getSession()->executeScript(
      "document.querySelector('tr[data-id=\"1\"] input[data-treegrid-control]').focus();",
    );
    // A real key press (keydown and keyup), so the checkbox gets its native
    // Space behavior.
    $session = $this->getSession()->getDriver()->getWebDriverSession();
    $key_actions = [
      'type' => 'key',
      'id' => 'keyboard',
      'actions' => [
        ['type' => 'keyDown', 'value' => ' '],
        ['type' => 'keyUp', 'value' => ' '],
      ],
    ];
    $session->postActions(['actions' => [$key_actions]]);
    $session->deleteActions();

    $this->assertTrue($this->getSession()->evaluateScript(
      "return document.querySelector('tr[data-id=\"1\"] input[data-treegrid-control]').checked;",
    ));
    $this->assertNothingPickedUp();
  }

  /**
   * A row without data-movable="true" can't be moved by any gesture.
   */
  public function testNonMovableRowsDoNotMove(): void {
    $this->openPage();
    $this->getSession()->executeScript(<<<'JS'
      document.querySelector('tr[data-id="3"] [data-treegrid-toggle]').click();
    JS);
    $this->assertJsCondition("document.querySelectorAll('tr[data-parent-id=\"3\"]').length === 3");
    $this->getSession()->executeScript(<<<'JS'
      window.moveCounts = { posts: 0 };
      const originalFetch = window.fetch;
      window.fetch = function (url, options) {
        if (options && options.method === 'POST') {
          window.moveCounts.posts += 1;
        }
        return originalFetch.apply(this, arguments);
      };
    JS);

    foreach (['3b', '3c'] as $id) {
      $this->pressKey($id, ' ');
      $this->assertNothingPickedUp();
      $this->pressKey($id, 'ArrowUp', TRUE);
      $this->pressKey($id, 'ArrowDown', TRUE);
      $this->dragHandle($id, '1');
    }
    usleep(500000);

    $this->assertSame(0, $this->getSession()->evaluateScript('return window.moveCounts.posts;'));
    \Drupal::state()->resetCache();
    $this->assertSame(0, (int) \Drupal::state()->get(TreegridReorderTestController::MOVE_HITS, 0));

    // A movable row in the same level does pick up.
    $this->pressKey('3a', ' ');
    $this->assertSame('true', $this->rowAttribute('3a', 'aria-selected'));
  }

  /**
   * After detach() Space picks nothing up.
   */
  public function testDetachTurnsReorderingOff(): void {
    $this->openPage();
    $this->assertSame('true', $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-ready');",
    ));

    $this->getSession()->executeScript("Drupal.detachBehaviors(document, drupalSettings, 'unload');");
    $this->pressKey('2', ' ');

    $this->assertNothingPickedUp();
    $this->assertNull($this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-ready');",
    ));
  }

  /**
   * A press outside the container cancels a move; one inside it doesn't.
   */
  public function testPressOutsideContainerCancelsMove(): void {
    $this->openPage();
    $this->pressKey('1', ' ');
    $this->assertSame('true', $this->rowAttribute('1', 'aria-selected'));

    $this->getSession()->getPage()->find('css', '[data-test-field="inside"]')->click();
    $this->assertSame('true', $this->rowAttribute('1', 'aria-selected'));

    $this->getSession()->getPage()->find('css', '[data-test-field="outside"]')->click();
    $this->assertJsCondition("!document.querySelector('tr[aria-selected]')");
  }

  /**
   * A refresh of the carried row's level puts it back and ends the move.
   */
  public function testRefreshDuringCarryRestoresRowAndEndsMove(): void {
    $this->openPage();
    $this->pressKey('1', ' ');
    $this->stepToDrop('1', 'after:3');
    $this->assertSame(['2', '3', '1', '4'], $this->topLevelOrder());

    $this->getSession()->executeScript("document.querySelector('drupal-treegrid').refreshLevel('');");
    $this->assertJsCondition("!document.querySelector('tr[aria-selected]')");

    $this->assertSame(['1', '2', '3', '4'], $this->topLevelOrder());
    $this->assertSame(1, $this->getSession()->evaluateScript("return document.querySelectorAll('tr[data-id=\"1\"]').length;"));
  }

  /**
   * Right onto a closed parent opens it and the carry continues as first child.
   */
  public function testRightOntoClosedParentOpensItAndCarryContinues(): void {
    $this->openPage();
    $this->expandRow('2');
    $this->pressKey('2c', ' ');
    $this->assertSame('after:2b', $this->dropTarget());

    $this->pressKey('2c', 'ArrowRight');
    $this->assertJsCondition("document.querySelectorAll('tr[data-parent-id=\"2b\"]').length === 2");

    $this->assertSame('true', $this->rowAttribute('2b', 'aria-expanded'));
    $this->assertSame('child:2b', $this->dropTarget());
    $this->assertSame(['2b', '2c', '2b1', '2b2'], $this->rowOrderFrom('2b', 4));
  }

  /**
   * Opening the previous sibling during a carry, then Escape, lands after it.
   */
  public function testEscapeAfterOpeningPreviousSiblingLandsAfterItsChildren(): void {
    $this->openPage();
    $this->expandRow('2');
    $this->pressKey('2c', ' ');
    $this->getSession()->executeScript("document.querySelector('tr[data-id=\"2b\"] [data-treegrid-toggle]').click();");
    $this->assertJsCondition("document.querySelectorAll('tr[data-parent-id=\"2b\"]').length === 2");

    $this->pressKey('2c', 'Escape');

    $this->assertSame(['2b', '2b1', '2b2', '2c'], $this->rowOrderFrom('2b', 4));
  }

  /**
   * Escape on a first child puts it back right after its parent.
   */
  public function testEscapeOnFirstChildLandsAfterParent(): void {
    $this->openPage();
    $this->expandRow('2');
    $this->pressKey('2a', ' ');
    $this->pressKey('2a', 'ArrowDown');
    $this->pressKey('2a', 'ArrowDown');
    $this->assertNotSame('before:2b', $this->dropTarget());
    $this->pressKey('2a', 'Escape');

    $this->assertSame(['2', '2a', '2b'], $this->rowOrderFrom('2', 3));
  }

  // Helpers.

  /**
   * Opens a parent row with its toggle and waits for its children.
   *
   * @param string $id
   *   The row's data-id.
   */
  private function expandRow(string $id): void {
    $this->getSession()->executeScript(sprintf(
      "document.querySelector('tr[data-id=\"%s\"] [data-treegrid-toggle]').click();",
      $id,
    ));
    $this->assertJsCondition(sprintf(
      "document.querySelectorAll('tr[data-parent-id=\"%s\"]').length > 0 && !document.querySelector('[data-treegrid-loading]')",
      $id,
    ));
  }

  /**
   * The ids of the rows from one row on, in DOM order.
   *
   * @param string $id
   *   The first row's data-id.
   * @param int $count
   *   How many rows to read.
   *
   * @return string[]
   *   The ids.
   */
  private function rowOrderFrom(string $id, int $count): array {
    $ids = $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('tr[role=\"row\"]')).map((row) => row.dataset.id);",
    );
    return array_slice($ids, array_search($id, $ids, TRUE), $count);
  }

  /**
   * The top-level row order in the DOM.
   *
   * @return string[]
   *   The row ids.
   */
  private function topLevelOrder(): array {
    return $this->getSession()->evaluateScript(
      "return Array.from(document.querySelectorAll('tr[data-parent-id=\"\"]')).map((row) => row.dataset.id);",
    );
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
  private function stepToDrop(string $id, string $wanted, string $key = 'ArrowDown'): void {
    for ($i = 0; $i < 12; $i++) {
      if ($this->dropTarget() === $wanted) {
        return;
      }
      $this->pressKey($id, $key);
    }
    $this->fail("Move cursor never reached '$wanted'.");
  }

  /**
   * Loads the test page and waits for the consumer to be wired.
   *
   * @param array<string, int|string> $query
   *   Optional query parameters.
   */
  private function openPage(array $query = []): void {
    $this->drupalGet('/treegrid-reorder-test', ['query' => $query]);
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-test-reorder-wired]'));
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
   * Drags a row's handle over another row, as a pointer would.
   *
   * @param string $source_id
   *   The data-id of the row whose handle is dragged.
   * @param string $target_id
   *   The data-id of the row it is released over.
   */
  private function dragHandle(string $source_id, string $target_id): void {
    $this->getSession()->executeScript(<<<JS
      const handle = document.querySelector('tr[data-id="$source_id"] [data-treegrid-reorder-handle]');
      const target = document.querySelector('tr[data-id="$target_id"]');
      const from = handle.getBoundingClientRect();
      const to = target.getBoundingClientRect();
      const send = (type, x, y) => handle.dispatchEvent(new PointerEvent(type, {
        bubbles: true, cancelable: true, composed: true, pointerId: 1,
        isPrimary: true, button: 0, clientX: x, clientY: y,
      }));
      send('pointerdown', from.left + 2, from.top + 2);
      send('pointermove', from.left + 12, from.top + 12);
      send('pointermove', to.left + 20, to.top + to.height * 0.1);
      send('pointerup', to.left + 20, to.top + to.height * 0.1);
    JS);
  }

  /**
   * Reads an attribute of a row.
   */
  private function rowAttribute(string $id, string $name): ?string {
    return $this->getSession()->evaluateScript(sprintf(
      "return document.querySelector('tr[role=\"row\"][data-id=\"%s\"]').getAttribute(%s);",
      $id,
      json_encode($name),
    ));
  }

  /**
   * The move's current drop position ("kind:id"), if a move is active.
   */
  private function dropTarget(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-drop');",
    );
  }

  /**
   * How many key events the consumer's own key handler has seen.
   */
  private function keyEventsSeenByConsumer(): int {
    return (int) $this->getSession()->evaluateScript('return window.reorderTestKeyEvents;');
  }

  /**
   * Asserts no row is picked up for a move.
   */
  private function assertNothingPickedUp(): void {
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "return document.querySelectorAll('tr[aria-selected], [data-treegrid-reorder-grabbed]').length;",
    ));
  }

}
