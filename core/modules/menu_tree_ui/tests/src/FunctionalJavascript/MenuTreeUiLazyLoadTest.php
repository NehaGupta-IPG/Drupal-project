<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests lazy-load edge cases in the tree grid.
 *
 * Covers the collapse-during-load race (H1): a parent collapsed while its
 * first children fetch is in flight must not show those children when the
 * fetch lands.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiLazyLoadTest extends MenuTreeUiJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['P'] = $this->createLink('Parent P', '', 0);
    $this->linkIds['C1'] = $this->createLink('Child C1', $this->linkIds['P'], 0);
    $this->linkIds['C2'] = $this->createLink('Child C2', $this->linkIds['P'], 1);
    // Two more top-level links so the level has more than the auto-expand
    // threshold (2): otherwise autoExpandSmall opens P on load and pre-loads
    // its children, and the collapse-during-first-load race never arises.
    $this->linkIds['Q'] = $this->createLink('Leaf Q', '', 1);
    $this->linkIds['R'] = $this->createLink('Leaf R', '', 2);
    $this->linkIds['E'] = $this->createLink('Emptied E', '', 3);
    $this->linkIds['E1'] = $this->createLink('Child E1', $this->linkIds['E'], 0);
  }

  /**
   * Collapsing a parent mid-load leaves its arriving children hidden.
   */
  public function testCollapseDuringLoadHidesChildren(): void {
    $pid = $this->linkIds['P'];

    // Fire expand then collapse synchronously on the toggle, so the collapse
    // runs while the first children fetch is still in flight (there are no
    // child rows yet for it to hide). The fetch is a network round-trip, so it
    // always resolves after these two synchronous clicks.
    $this->getSession()->evaluateScript(<<<JS
      (() => {
        const row = document.querySelector('tr[data-id="{$pid}"]');
        const toggle = row.querySelector('[data-treegrid-toggle]');
        toggle.click();
        toggle.click();
      })();
    JS);

    // Wait for the in-flight load to settle and its rows to arrive in the DOM.
    $this->assertJsCondition(
      "document.querySelector('tr[data-id=\"{$pid}\"]').dataset.treegridLoading === undefined",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('tr[data-parent-id=\"{$pid}\"]').length > 0",
    );

    // The parent is collapsed, and every child that arrived is hidden, so none
    // is visible under the closed row.
    $this->assertSame('false', $this->row('P')->getAttribute('aria-expanded'));
    $visible_children = (int) $this->getSession()->evaluateScript(<<<JS
      (() => Array.from(
        document.querySelectorAll('tr[data-parent-id="{$pid}"]'),
      ).filter((r) => r.dataset.treegridHidden !== 'true').length)();
    JS);
    $this->assertSame(0, $visible_children, 'No children are visible under the collapsed parent.');
  }

  /**
   * A parent whose children are gone shows its spacer once the level loads.
   */
  public function testEmptyLoadRemovesTheToggle(): void {
    $uuid = substr($this->linkIds['E1'], strlen('menu_link_content:'));
    \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', $uuid)->delete();

    $this->row('E')->find('css', '.menu-tree-ui-row__name button.menu-tree-ui-row__toggle')->click();

    $this->assertJsCondition(sprintf(
      "document.querySelector('tr[data-id=\"%s\"]').dataset.hasChildren === 'false'",
      $this->linkIds['E'],
    ));
    $this->assertIsLeafRow('E');
  }

}
