<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Psr\Log\LoggerInterface;

/**
 * Moves a menu link to a new parent + position.
 *
 * Read paths live on MenuTreeLoader; this service owns the single write
 * operation in v1 (drag-and-drop / keyboard reorder). Keeping reads and writes
 * apart makes the responsibilities obvious and the controller DI shape clearer.
 *
 * Both menu_link_content links and static (*.links.menu.yml) links that support
 * parent/weight overrides are moveable — see MenuLinkMovability. Writes go
 * through the menu link manager, which persists to the entity or to
 * StaticMenuLinkOverrides as appropriate, exactly as core's menu overview form
 * does. Links whose plugin can't persist the change are refused up front.
 *
 * Extension without subclassing:
 *   - hook_menu_tree_ui_pre_move_alter() — veto a move before validation.
 *   - hook_menu_tree_ui_post_move() — react after a successful move.
 */
final class MenuTreeMover {

  public function __construct(
    private readonly MenuTreeStorageInterface $treeStorage,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly MenuTreeLoader $loader,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Writes a validated move to live storage (no transaction, no validation).
   *
   * Renumbers the destination siblings first — reading their current order
   * (before the move) and reweighting everything except the moved link, which
   * yields the weight the moved link should take — then writes the moved link's
   * parent and weight together through the menu link manager, which routes to
   * the entity or to StaticMenuLinkOverrides. Callers own the transaction so a
   * single move and a batched Save can each wrap their work atomically without
   * nesting transactions.
   *
   * @param string $menu_name
   *   The menu the move happens within.
   * @param string $moved_id
   *   Plugin ID of the link being moved.
   * @param string $new_parent_id
   *   Plugin ID of the new parent, or '' for the menu root.
   * @param string|null $before_id
   *   Plugin ID of the sibling the moved link should sit before, or NULL.
   *
   * @return int
   *   The weight the moved link was given.
   */
  private function persistMove(
    string $menu_name,
    string $moved_id,
    string $new_parent_id,
    ?string $before_id,
  ): int {
    $new_weight = $this->renumberSiblings($moved_id, $menu_name, $new_parent_id, $before_id);
    $this->menuLinkManager->updateDefinition($moved_id, [
      'parent' => $new_parent_id,
      'weight' => $new_weight,
    ]);
    return $new_weight;
  }

  /**
   * Validates a move without persisting it.
   *
   * Runs the pre-move veto hook and every structural check (movability, menu
   * match, self-target, cycle, depth cap) against the live tree. The editing
   * UI records a move only after this passes, and Save re-runs the full move()
   * — which calls this again — so a move that became invalid after an earlier
   * pending change (or a concurrent edit) is caught at commit time.
   *
   * @param string $menu_name
   *   The menu the move is happening within.
   * @param string $moved_id
   *   Plugin ID of the link being moved.
   * @param string $new_parent_id
   *   Plugin ID of the new parent, or empty string for the menu root.
   * @param string|null $before_id
   *   Plugin ID of the sibling the moved link should sit before, or NULL.
   *
   * @return array{ok: bool, code?: string, message?: string}
   *   ['ok' => TRUE] when the move is allowed, otherwise an error outcome.
   */
  public function validateMove(
    string $menu_name,
    string $moved_id,
    string $new_parent_id,
    ?string $before_id,
  ): array {
    // Allow modules to veto the move before any validation runs.
    // Implementing hook_menu_tree_ui_pre_move_alter() and setting
    // $veto['veto'] = TRUE will block the move; set $veto['message'] to
    // provide a user-facing reason.
    $veto = [];
    $this->moduleHandler->alter('menu_tree_ui_pre_move', $veto, $menu_name, $moved_id, $new_parent_id, $before_id);
    if (!empty($veto['veto'])) {
      return [
        'ok' => FALSE,
        'code' => 'vetoed',
        'message' => $veto['message'] ?? 'Move blocked by a module.',
      ];
    }

    $link = $this->loadLink($moved_id);
    if ($link === NULL || !MenuLinkMovability::isMovable($link)) {
      return [
        'ok' => FALSE,
        'code' => 'not_moveable',
        'message' => 'This menu link cannot be reordered.',
      ];
    }
    if ($link->getMenuName() !== $menu_name) {
      return [
        'ok' => FALSE,
        'code' => 'menu_mismatch',
        'message' => 'Link belongs to a different menu.',
      ];
    }

    if ($new_parent_id === $moved_id) {
      return ['ok' => FALSE, 'code' => 'self_target', 'message' => 'Cannot drop a link onto itself.'];
    }

    if ($new_parent_id !== '' && $this->isDescendant($new_parent_id, $moved_id)) {
      return ['ok' => FALSE, 'code' => 'cycle', 'message' => 'Cannot drop a link into one of its own descendants.'];
    }

    if (!$this->fitsWithinDepthCap($moved_id, $new_parent_id)) {
      return [
        'ok' => FALSE,
        'code' => 'too_deep',
        'message' => 'Moving here would exceed the 9-level depth limit.',
      ];
    }

    return ['ok' => TRUE];
  }

  /**
   * Commits a menu's pending working-copy moves to live storage.
   *
   * Validates every recorded move against live storage first, so a move that is
   * individually invalid (unknown, immovable, cross-menu, self, cycle, too
   * deep) aborts the commit before anything is written. It then applies the
   * moves in order, re-validating each immediately before its write so a move
   * made invalid by an earlier applied one (e.g. a newly formed cycle) is also
   * caught. Menu link writes are not safe to wrap in a database transaction —
   * the menu storage rebuild closes it — so this is ordered, best-effort
   * application, matching core's menu overview form. Non-move ops are ignored
   * (column toggles and entity edits commit on their own). The caller clears
   * the working copy after a successful commit.
   *
   * @param string $menu_name
   *   The menu whose working copy to commit.
   * @param array<int, array<string, mixed>> $ops
   *   The recorded ops, in order.
   *
   * @return array{ok: bool, code?: string, message?: string, failed_op?: int}
   *   ['ok' => TRUE] on success, otherwise the first failing op's outcome.
   */
  public function commitWorkingCopy(string $menu_name, array $ops): array {
    $moves = array_values(array_filter(
      $ops,
      static fn(array $op): bool => ($op['type'] ?? '') === 'move',
    ));
    if ($moves === []) {
      return ['ok' => TRUE];
    }

    $normalized = array_map(static function (array $op): array {
      $before_id = $op['before_id'] ?? NULL;
      return [
        'id' => (string) ($op['id'] ?? ''),
        'new_parent' => (string) ($op['new_parent'] ?? ''),
        'before_id' => $before_id === NULL ? NULL : (string) $before_id,
      ];
    }, $moves);

    // Pre-flight: reject the batch if any op is individually invalid, before
    // writing anything.
    foreach ($normalized as $index => $op) {
      $validation = $this->validateMove($menu_name, $op['id'], $op['new_parent'], $op['before_id']);
      if (!$validation['ok']) {
        return $this->commitFailure($validation, $index);
      }
    }

    // Apply in order, re-validating against the now-current live state.
    foreach ($normalized as $index => $op) {
      $validation = $this->validateMove($menu_name, $op['id'], $op['new_parent'], $op['before_id']);
      if (!$validation['ok']) {
        return $this->commitFailure($validation, $index);
      }
      $weight = $this->persistMove($menu_name, $op['id'], $op['new_parent'], $op['before_id']);
      $this->moduleHandler->invokeAll(
        'menu_tree_ui_post_move',
        [$menu_name, $op['id'], $op['new_parent'], $weight],
      );
    }

    return ['ok' => TRUE];
  }

  /**
   * Shapes a failed-op outcome for commitWorkingCopy().
   *
   * @param array{code?: string, message?: string} $validation
   *   The failing validateMove() result.
   * @param int $index
   *   The index of the failing move within the batch.
   *
   * @return array{ok: false, code: string, message: string, failed_op: int}
   *   The commit failure outcome.
   */
  private function commitFailure(array $validation, int $index): array {
    return [
      'ok' => FALSE,
      'code' => $validation['code'] ?? 'commit_failed',
      'message' => $validation['message'] ?? 'A pending change could not be saved.',
      'failed_op' => $index,
    ];
  }

  /**
   * Loads the menu link plugin instance by plugin ID, or NULL if unknown.
   */
  private function loadLink(string $plugin_id): ?MenuLinkInterface {
    if (!$this->menuLinkManager->hasDefinition($plugin_id)) {
      return NULL;
    }
    return $this->menuLinkManager->createInstance($plugin_id);
  }

  /**
   * Walks ancestors of $candidate_parent_id; TRUE if $moved_id appears there.
   *
   * Uses the materialized path (getRootPathIds) — O(1) lookups against the
   * menu_tree table rather than walking with N entity loads.
   */
  private function isDescendant(string $candidate_parent_id, string $moved_id): bool {
    $path_ids = $this->treeStorage->getRootPathIds($candidate_parent_id);
    return in_array($moved_id, $path_ids, TRUE);
  }

  /**
   * Checks that moved subtree height + new parent depth <= core's 9 limit.
   */
  private function fitsWithinDepthCap(string $moved_id, string $new_parent_id): bool {
    $max = $this->treeStorage->maxDepth();
    $new_parent_depth = $new_parent_id === '' ? 0 : count($this->treeStorage->getRootPathIds($new_parent_id));
    $moved_height = $this->treeStorage->getSubtreeHeight($moved_id);
    return ($new_parent_depth + $moved_height) <= $max;
  }

  /**
   * Renumbers destination siblings so the moved link lands at the right spot.
   *
   * Works on the canonical sibling order the UI shows — every link type, not
   * just menu_link_content. That matters for two reasons:
   *  - $before_id may point at a static (module-defined) link such as the front
   *    page; an entity-only query would never find it, so the moved link would
   *    silently fall through to the end of the list.
   *  - Static links must be reweighted alongside content links to keep the
   *    overall order intact — exactly as core's menu overview form does via
   *    MenuLinkManager::updateDefinition(). Leaving them untouched is what made
   *    a link dropped above the front page jump to the bottom.
   *
   * Called before the moved link is updated, so the loaded order reflects the
   * pre-move state; the moved link is spliced in by plugin ID. The caller
   * persists the moved link's own parent + weight, so this method skips it and
   * only writes the *other* siblings.
   *
   * @param string $moved_id
   *   Plugin ID of the link being moved.
   * @param string $menu_name
   *   The menu the move happens within.
   * @param string $parent_id
   *   Plugin ID of the destination parent, or '' for the menu root.
   * @param string|null $before_id
   *   Plugin ID of the sibling the moved link should sit before, or NULL to
   *   drop at the end.
   *
   * @return int
   *   The new weight the moved link should take.
   */
  private function renumberSiblings(
    string $moved_id,
    string $menu_name,
    string $parent_id,
    ?string $before_id,
  ): int {
    $ordered = [];
    $current_weight = [];
    foreach ($this->loader->loadLevel($menu_name, $parent_id === '' ? NULL : $parent_id) as $element) {
      $id = $element->link->getPluginId();
      $ordered[] = $id;
      $current_weight[$id] = (int) $element->link->getWeight();
    }

    // Pluck the moved link out and splice it back in before $before_id (or at
    // the end). Shared with MenuTreeLoader::spliceBefore() so the read overlay
    // and this write-side reweight produce the same order.
    $reordered = MenuTreeLoader::spliceBefore($ordered, $moved_id, $before_id);

    $assigned_weight = 0;
    foreach ($reordered as $offset => $id) {
      if ($id === $moved_id) {
        $assigned_weight = $offset;
        // The caller writes the moved link's parent + weight in one save.
        continue;
      }
      if (($current_weight[$id] ?? NULL) !== $offset) {
        try {
          $this->menuLinkManager->updateDefinition($id, ['weight' => $offset]);
        }
        catch (PluginException $e) {
          // A sibling whose backing entity has gone (an orphaned menu link)
          // must not abort the whole save. Skip it - it keeps its old weight,
          // the remaining links stay correctly ordered - and log it so the
          // stale row can be cleaned up.
          $this->logger->warning('Could not reweight menu link %id while saving menu %menu; its backing entity is missing. %message', [
            '%id' => $id,
            '%menu' => $menu_name,
            '%message' => $e->getMessage(),
          ]);
        }
      }
    }
    return $assigned_weight;
  }

}
