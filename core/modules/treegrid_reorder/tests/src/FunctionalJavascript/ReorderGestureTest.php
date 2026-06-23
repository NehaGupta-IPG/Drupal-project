<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_reorder_test\TestTreeMoveHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises the reorder gestures within treegrid_reorder itself.
 *
 * The treegrid_reorder_test module is a minimal consumer (a flat list of four
 * movable rows backed by State), so this covers the generic drag, keyboard
 * move-mode, and Shift+Arrow gesture code without going through menu_tree_ui.
 * Each gesture is checked for both its visible DOM effect and that the move
 * reached storage through TreeReorderManager + the test handler.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
final class ReorderGestureTest extends WebDriverTestBase {

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
   * Pointer drag reorders a row and persists the new order.
   */
  public function testPointerDragReorder(): void {
    // Drag item 3 into the "before" zone of item 1: 3, 1, 2, 4.
    $this->dragBefore('3', '1');
    $this->settle();

    $this->assertRowOrder(['3', '1', '2', '4']);
    $this->assertPersistedOrder(['3', '1', '2', '4']);
  }

  /**
   * Shift+ArrowDown swaps a row with its next sibling and persists.
   */
  public function testShiftArrowReorder(): void {
    // Move item 1 down past item 2: 2, 1, 3, 4.
    $this->dispatchKey('1', 'ArrowDown', shift: TRUE);

    $this->assertRowOrder(['2', '1', '3', '4']);
    $this->assertPersistedOrder(['2', '1', '3', '4']);
  }

  /**
   * Keyboard move mode (Space, arrows, Enter) reorders and persists.
   */
  public function testKeyboardMoveMode(): void {
    // Grab item 1, walk the cursor to "after the last item", drop: 2, 3, 4, 1.
    $this->dispatchKey('1', ' ');
    $this->stepToDrop('1', 'after:4');
    $this->dispatchKey('1', 'Enter');
    $this->settle();

    $this->assertRowOrder(['2', '3', '4', '1']);
    $this->assertPersistedOrder(['2', '3', '4', '1']);
  }

  // Helpers.

  /**
   * Loads the test page and waits for the upgraded treegrid.
   */
  private function openPage(): void {
    $this->drupalGet('/treegrid-reorder-test');
    $this->assertSession()->waitForElement(
      'css',
      'drupal-treegrid table[role="treegrid"] tbody tr[role="row"]',
    );
  }

  /**
   * The current top-level row order as data-id strings, in DOM order.
   *
   * @return string[]
   *   The row IDs.
   */
  private function rowOrder(): array {
    $ids = [];
    $rows = $this->getSession()->getPage()->findAll(
      'css',
      'drupal-treegrid tr[role="row"]',
    );
    foreach ($rows as $row) {
      $ids[] = $row->getAttribute('data-id');
    }
    return $ids;
  }

  /**
   * Asserts the top-level DOM row order, polling for the commit to land.
   *
   * The server-first commit reorders the DOM only once the move POST and
   * refresh land, and settle() can return before that starts, so wait for the
   * expected order rather than reading it immediately.
   *
   * @param string[] $expected
   *   The expected ordered list of row data-ids.
   */
  private function assertRowOrder(array $expected): void {
    $this->getSession()->wait(
      10000,
      sprintf(
        "Array.from(document.querySelectorAll('drupal-treegrid tr[role=\"row\"]')).map((r) => r.dataset.id).join(',') === %s",
        json_encode(implode(',', $expected)),
      ),
    );
    $this->assertSame($expected, $this->rowOrder());
  }

  /**
   * Waits for any in-flight move commit to finish.
   */
  private function settle(): void {
    $this->getSession()->wait(
      5000,
      '!document.querySelector(\'drupal-treegrid[data-treegrid-moving="true"]\')',
    );
  }

  /**
   * Drags one row's handle into the "before" zone of another row.
   *
   * Fires the same synthetic PointerEvent sequence the menu drag test uses
   * against the live DragController.
   *
   * @param string $source_id
   *   The data-id of the row to move.
   * @param string $target_id
   *   The data-id of the row to drop before.
   */
  private function dragBefore(string $source_id, string $target_id): void {
    $script = <<<JS
      const sourceRow = document.querySelector('tr[role="row"][data-id="$source_id"]');
      const targetRow = document.querySelector('tr[role="row"][data-id="$target_id"]');
      if (!sourceRow || !targetRow) throw new Error('source or target row not in DOM');
      targetRow.scrollIntoView({block: 'center'});
      const handle = sourceRow.querySelector('.reorder-test-handle');
      if (!handle) throw new Error('no handle on source row');
      const hRect = handle.getBoundingClientRect();
      const tRect = targetRow.getBoundingClientRect();
      const startX = hRect.left + hRect.width / 2;
      const startY = hRect.top + hRect.height / 2;
      const endX = tRect.left + tRect.width / 2;
      const endY = tRect.top + tRect.height * 0.1;
      function send(t, type, x, y) {
        t.dispatchEvent(new PointerEvent(type, {
          bubbles: true, cancelable: true, composed: true,
          pointerId: 1, isPrimary: true, button: 0,
          clientX: x, clientY: y, screenX: x, screenY: y,
        }));
      }
      send(handle, 'pointerdown', startX, startY);
      send(handle, 'pointermove', startX + 10, startY + 10);
      send(handle, 'pointermove', endX, endY);
      send(handle, 'pointerup', endX, endY);
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Focuses a row and dispatches one keydown on it.
   *
   * @param string $id
   *   The data-id of the row.
   * @param string $key
   *   The KeyboardEvent key value (e.g. ' ', 'ArrowDown', 'Enter').
   * @param bool $shift
   *   Whether the Shift modifier is held.
   */
  private function dispatchKey(string $id, string $key, bool $shift = FALSE): void {
    $key_json = json_encode($key);
    $shift_json = $shift ? 'true' : 'false';
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row not in DOM');
      row.scrollIntoView({block: 'center'});
      row.tabIndex = 0;
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: $key_json, shiftKey: $shift_json, bubbles: true, cancelable: true,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Steps the move-mode cursor with ArrowDown until it reaches a drop position.
   *
   * Move mode records the live drop position on the host as data-treegrid-drop
   * ("kind:id"); walking to a wanted value is deterministic regardless of where
   * the cursor starts.
   *
   * @param string $grabbed_id
   *   The data-id of the grabbed (focused) row.
   * @param string $wanted
   *   The wanted data-treegrid-drop value, "kind:id".
   */
  private function stepToDrop(string $grabbed_id, string $wanted): void {
    for ($i = 0; $i < 10; $i++) {
      $drop = $this->getSession()->evaluateScript(
        "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-drop');",
      );
      if ($drop === $wanted) {
        return;
      }
      $this->dispatchKey($grabbed_id, 'ArrowDown');
    }
    $this->fail("Move cursor never reached '$wanted'.");
  }

  /**
   * Asserts the order persisted to State, polling for the async move POST.
   *
   * @param string[] $expected
   *   The expected ordered list of item IDs.
   */
  private function assertPersistedOrder(array $expected): void {
    $state = \Drupal::state();
    for ($i = 0; $i < 50; $i++) {
      $state->resetCache();
      if ($state->get(TestTreeMoveHandler::STATE_KEY, TestTreeMoveHandler::DEFAULT_ORDER) === $expected) {
        return;
      }
      usleep(100000);
    }
    $state->resetCache();
    $this->assertSame(
      $expected,
      $state->get(TestTreeMoveHandler::STATE_KEY, TestTreeMoveHandler::DEFAULT_ORDER),
      'The reorder persisted to State.',
    );
  }

}
