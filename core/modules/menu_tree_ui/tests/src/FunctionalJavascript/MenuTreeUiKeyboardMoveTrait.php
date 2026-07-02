<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

/**
 * Keyboard grab/select move drivers for menu_tree_ui tests.
 *
 * Dispatches keydown sequences at the grabbed row and walks the move cursor
 * deterministically via the host's data-treegrid-drop marker. Expects the using
 * class to extend MenuTreeUiJavascriptTestBase (for $linkIds and settle()).
 */
trait MenuTreeUiKeyboardMoveTrait {

  /**
   * Grabs $source, walks the cursor to "$kind:$target", drops, and settles.
   */
  private function moveTo(string $source, string $kind, string $target_label): void {
    $this->dispatchKeys($source, [' ']);
    $this->stepToDrop($source, $kind . ':' . $this->linkIds[$target_label]);
    $this->dispatchKeys($source, ['Enter']);
    $this->settle();
  }

  /**
   * Walks the move cursor to a wanted "kind:id" drop position.
   *
   * The cursor is two-dimensional: Up/Down move it one visual position and
   * Left/Right change the landing depth. This parks at the top, then for each
   * gap sweeps depth from shallowest (Left) to deepest (Right), checking the
   * host's data-treegrid-drop after each press — deterministic regardless of
   * where the grab began. Right may open a collapsed parent (the documented
   * behavior), which only adds reachable positions.
   *
   * @param string $source
   *   Label of the grabbed row (the node keydown events are dispatched on).
   * @param string $want
   *   The wanted data-treegrid-drop value, "kind:pluginId".
   * @param int $max
   *   Safety bound on the number of gaps / depths swept.
   */
  private function stepToDrop(string $source, string $want, int $max = 40): void {
    $this->dispatchKeys($source, array_fill(0, $max, 'ArrowUp'));
    for ($gap = 0; $gap <= $max; $gap++) {
      $this->dispatchKeys($source, array_fill(0, $max, 'ArrowLeft'));
      for ($depth = 0; $depth <= $max; $depth++) {
        if ($this->readDrop() === $want) {
          return;
        }
        $before = $this->readDrop();
        $this->dispatchKeys($source, ['ArrowRight']);
        if ($this->readDrop() === $before) {
          break;
        }
      }
      $this->dispatchKeys($source, ['ArrowDown']);
    }
    $this->fail(sprintf(
      "Move cursor never reached '%s'; last was %s.",
      $want,
      var_export($this->readDrop(), TRUE),
    ));
  }

  /**
   * Reads the host's current prospective drop ("kind:id"), or NULL.
   */
  private function readDrop(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.querySelector('drupal-treegrid').getAttribute('data-treegrid-drop');",
    );
  }

  /**
   * Focuses the source row and dispatches a sequence of keydown events on it.
   *
   * The keydown events bubble to the tree, where the <drupal-treegrid> element
   * routes them to the MoveModeController. Focus stays on the grabbed row
   * throughout move mode, so the whole sequence targets one node.
   *
   * @param string $source_label
   *   The label of the row to act on.
   * @param string[] $keys
   *   KeyboardEvent.key values to dispatch in order (e.g. ' ', 'ArrowDown').
   */
  private function dispatchKeys(string $source_label, array $keys): void {
    $source_id = $this->linkIds[$source_label] ?? throw new \InvalidArgumentException("Unknown source label: $source_label");
    $keys_json = json_encode(array_values($keys));

    $script = <<<JS
      const source = document.querySelector('tr[role="row"][data-id="$source_id"]');
      if (!source) throw new Error('source row not in DOM');
      source.scrollIntoView({block: 'center'});
      source.tabIndex = 0;
      source.focus();
      for (const k of $keys_json) {
        source.dispatchEvent(new KeyboardEvent('keydown', {
          key: k, bubbles: true, cancelable: true,
        }));
      }
    JS;

    $this->getSession()->executeScript($script);
  }

  /**
   * Dispatches a click on a row at a vertical zone (0 = top, 1 = bottom).
   *
   * The click lands on the title so it never hits a row control, and carries a
   * clientY in the chosen zone so the move-mode intent (before/child/after) is
   * computed from it.
   *
   * @param string $label
   *   The row label to click.
   * @param float $ratio
   *   The vertical position within the row, 0..1.
   */
  private function clickRowZone(string $label, float $ratio): void {
    $id = $this->linkIds[$label];
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      const rect = row.getBoundingClientRect();
      const x = rect.left + rect.width * 0.5;
      const y = rect.top + rect.height * $ratio;
      const hit = row.querySelector('.menu-tree-ui-row__title') || row;
      hit.dispatchEvent(new MouseEvent('click', {
        bubbles: true, cancelable: true, clientX: x, clientY: y,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * Asserts a held row is in the DOM, not hidden, and laid out (visible).
   *
   * @param string $label
   *   The held row's label.
   */
  private function assertHeldRowVisible(string $label): void {
    $id = $this->linkIds[$label];
    $script = <<<JS
      return (function () {
        const row = document.querySelector('tr[role="row"][data-id="$id"]');
        if (!row) return 'missing';
        if (row.dataset.hidden === 'true') return 'data-hidden';
        const visible = typeof row.checkVisibility === 'function'
          ? row.checkVisibility()
          : row.offsetParent !== null;
        return visible ? 'visible' : 'not-visible';
      })();
    JS;
    $this->assertSame(
      'visible',
      (string) $this->getSession()->evaluateScript($script),
      "Held row $label should remain visible.",
    );
  }

}
