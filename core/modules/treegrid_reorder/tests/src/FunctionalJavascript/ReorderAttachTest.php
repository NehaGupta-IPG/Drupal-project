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
   * Outside a move Space on the in-row checkbox toggles it and picks nothing up.
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

  // Helpers.

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
