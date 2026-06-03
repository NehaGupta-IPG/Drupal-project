<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\menu_tree_ui\MenuLinkMovability;

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
    private readonly Connection $database,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly MenuTreeLoader $loader,
    private readonly MenuLinkManagerInterface $menuLinkManager,
  ) {}

  /**
   * Applies a move and returns an outcome the controller can serialise.
   *
   * @param string $menu_name
   *   The menu the move is happening within.
   * @param string $moved_id
   *   Plugin ID of the link being moved.
   * @param string $new_parent_id
   *   Plugin ID of the new parent, or empty string for the menu root.
   * @param string|null $before_id
   *   Plugin ID of the sibling that should sit immediately after the moved
   *   link, or NULL to drop at the end of the parent's children list.
   *
   * @return array{ok: bool, code?: string, message?: string, weight?: int}
   *   On success: ['ok' => TRUE, 'weight' => int].
   *   On failure: ['ok' => FALSE, 'code' => string, 'message' => string].
   */
  public function move(
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

    // Wrap the sibling renumber + the moved link's update in a transaction so a
    // mid-operation failure cannot leave siblings with stale weights under the
    // new parent.
    $txn = $this->database->startTransaction();
    try {
      // Renumber the destination siblings first — this reads their current
      // order (before the move) and reweights everything except the moved link,
      // returning the weight the moved link should take. The moved link's
      // parent and weight are then written together through the menu link
      // manager, which routes to the entity or to StaticMenuLinkOverrides.
      $new_weight = $this->renumberSiblings($moved_id, $menu_name, $new_parent_id, $before_id);
      $this->menuLinkManager->updateDefinition($moved_id, [
        'parent' => $new_parent_id,
        'weight' => $new_weight,
      ]);
    }
    catch (\Exception $e) {
      $txn->rollBack();
      throw $e;
    }

    $result = ['ok' => TRUE, 'weight' => $new_weight];

    $this->moduleHandler->invokeAll(
      'menu_tree_ui_post_move',
      [$menu_name, $moved_id, $new_parent_id, $new_weight],
    );

    return $result;
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
   * Uses the materialised path (getRootPathIds) — O(1) lookups against the
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

    // Pluck the moved link out, then splice it back in before $before_id (or
    // push to the end when there is no anchor, or the anchor has vanished).
    $without_moved = array_values(array_filter(
      $ordered,
      static fn(string $id): bool => $id !== $moved_id,
    ));
    $reordered = [];
    $inserted = FALSE;
    foreach ($without_moved as $id) {
      if (!$inserted && $before_id !== NULL && $id === $before_id) {
        $reordered[] = $moved_id;
        $inserted = TRUE;
      }
      $reordered[] = $id;
    }
    if (!$inserted) {
      $reordered[] = $moved_id;
    }

    $assigned_weight = 0;
    foreach ($reordered as $offset => $id) {
      if ($id === $moved_id) {
        $assigned_weight = $offset;
        // The caller writes the moved link's parent + weight in one save.
        continue;
      }
      if (($current_weight[$id] ?? NULL) !== $offset) {
        $this->menuLinkManager->updateDefinition($id, ['weight' => $offset]);
      }
    }
    return $assigned_weight;
  }

}
