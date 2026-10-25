<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\treegrid_reorder_test\Controller\TreegridReorderTestController;
use Drupal\workspaces\Entity\Workspace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the needs-reload message area of the menu tree page.
 *
 * The treegrid_reorder_test module's echo route stands in for an endpoint that
 * answers stale. The Reload control is located by its data attribute.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiNeedsReloadTest extends MenuTreeUiJavascriptTestBase {

  use MenuTreeUiDragGestureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_tree_ui',
    'treegrid_reorder_test',
    'workspaces',
  ];

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    foreach (['A', 'B', 'C'] as $weight => $label) {
      $this->linkIds[$label] = $this->createLink($label, '', $weight);
    }
  }

  /**
   * A stale response shows the Reload control and refuses moves and Save.
   */
  public function testStaleShowsReloadAndRefusesMovesAndSave(): void {
    \Drupal::state()->set(TreegridReorderTestController::REVISION, 'theirs');
    $this->logRequests();

    $echo_url = Url::fromRoute('treegrid_reorder_test.echo')->toString();
    $this->getSession()->executeScript(<<<JS
      Drupal.treegridReorder.request(document.querySelector('drupal-treegrid'), '$echo_url', { body: {} });
    JS);

    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '[data-mtu-action="reload"]'));
    $this->assertSame('stale', $this->needsReloadReason());

    $this->dispatchShiftArrow('A', 'ArrowDown');
    $this->getSession()->getPage()->find('css', '[data-mtu-action="save"]')->click();
    usleep(600000);

    $requests = $this->getSession()->evaluateScript('return window.requestLog;');
    $this->assertNotContains('move', $requests);
    $this->assertNotContains('save', $requests);
    $this->assertSiblings(['A', 'B', 'C'], $this->childRowsSelector(NULL));
  }

  /**
   * The Reload button is described by the message.
   */
  public function testReloadButtonIsDescribedByTheMessage(): void {
    \Drupal::state()->set(TreegridReorderTestController::REVISION, 'theirs');
    $echo_url = Url::fromRoute('treegrid_reorder_test.echo')->toString();
    $this->getSession()->executeScript(<<<JS
      Drupal.treegridReorder.request(document.querySelector('drupal-treegrid'), '$echo_url', { body: {} });
    JS);
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '[data-mtu-action="reload"]'));

    $resolves = $this->getSession()->evaluateScript(<<<'JS'
      (() => {
        const button = document.querySelector('[data-mtu-action="reload"]');
        return document.getElementById(button.getAttribute('aria-describedby'))
          === document.querySelector('[data-mtu-needs-reload-message]');
      })();
    JS);
    $this->assertTrue($resolves);
  }

  /**
   * A failed redraw shows the Reload control, and Save is still sent.
   */
  public function testRefreshFailureShowsReloadAndSaveStillSent(): void {
    $this->logRequests(fail_children_after_move: TRUE);

    $this->drag('C', 'A', 'before');
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '[data-mtu-action="reload"]'));
    $this->assertSame('refresh-failed', $this->needsReloadReason());

    $this->getSession()->getPage()->find('css', '[data-mtu-action="save"]')->click();
    $this->assertJsCondition("window.requestLog.includes('save')");
    $this->assertJsCondition("document.querySelector('.menu-tree-ui__unsaved').classList.contains('js-hide')");
  }

  /**
   * A pending revision made after page load refuses a move and needs a reload.
   */
  public function testPendingRevisionRefusesMoveAndNeedsReload(): void {
    $this->logRequests();
    Workspace::create(['id' => 'stage', 'label' => 'Stage'])->save();
    $link = $this->loadLinkByPluginId($this->linkIds['A']);
    \Drupal::service('workspaces.manager')->executeInWorkspace('stage', static function () use ($link): void {
      $stage_link = MenuLinkContent::load($link->id());
      $stage_link->set('title', 'A in stage');
      $stage_link->setNewRevision();
      $stage_link->save();
    });

    $this->dispatchShiftArrow('B', 'ArrowDown');
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '[data-mtu-action="reload"]'));
    $this->assertSame('pending-revisions', $this->needsReloadReason());
    $this->assertSiblings(['A', 'B', 'C'], $this->childRowsSelector(NULL));

    // The next move gesture is refused without a request.
    $moves_before = $this->countRequests('move');
    $this->dispatchShiftArrow('C', 'ArrowUp');
    usleep(500000);
    $this->assertSame($moves_before, $this->countRequests('move'));
  }

  // Helpers.

  /**
   * Loads a menu link content entity by its plugin ID.
   */
  private function loadLinkByPluginId(string $plugin_id): MenuLinkContent {
    $uuid = substr($plugin_id, strlen('menu_link_content:'));
    $entities = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['uuid' => $uuid]);
    return reset($entities);
  }

  /**
   * Logs the move, Save and children requests in window.requestLog.
   *
   * @param bool $fail_children_after_move
   *   Whether every children request after a move answers 500.
   */
  private function logRequests(bool $fail_children_after_move = FALSE): void {
    $fail = $fail_children_after_move ? 'true' : 'false';
    $this->getSession()->executeScript(<<<JS
      window.requestLog = [];
      let moved = false;
      const originalFetch = window.fetch;
      window.fetch = async function (url, options) {
        const text = String(url);
        const post = options && options.method === 'POST';
        if (post && text.includes('/move')) {
          window.requestLog.push('move');
          moved = true;
        }
        else if (post && text.includes('/save')) {
          window.requestLog.push('save');
        }
        else if (!post && text.includes('/children') && moved && $fail) {
          return new Response(JSON.stringify({ ok: false }), { status: 500 });
        }
        return originalFetch.apply(this, arguments);
      };
    JS);
  }

  /**
   * How many requests of a kind the page has logged.
   */
  private function countRequests(string $kind): int {
    return count(array_filter(
      $this->getSession()->evaluateScript('return window.requestLog;'),
      static fn (string $request): bool => $request === $kind,
    ));
  }

  /**
   * The tree's needs-reload reason, if any.
   */
  private function needsReloadReason(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-needs-reload');",
    );
  }

  /**
   * Focuses a row and dispatches a Shift+arrow keydown at it.
   */
  private function dispatchShiftArrow(string $label, string $key): void {
    $id = $this->linkIds[$label];
    $this->getSession()->executeScript(<<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      row.tabIndex = 0;
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: '$key', shiftKey: true, bubbles: true, cancelable: true,
      }));
    JS);
  }

}
