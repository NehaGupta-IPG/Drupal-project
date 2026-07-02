<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\menu_tree_ui\MenuLinkMovability;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeMoveHandlerInterface;
use Drupal\treegrid_reorder\TreeReorderManager;
use Psr\Log\LoggerInterface;

/**
 * Moves a menu link to a new parent + position.
 *
 * Read paths live on MenuTreeLoader; this service owns the single write
 * operation in v1 (drag-and-drop / keyboard reorder). The universal parts of a
 * move — the self-target and cycle checks, and the validate-then-apply commit
 * loop — belong to TreeReorderManager; this class is the menu move handler it
 * delegates to, supplying the menu-specific rules and the write. Its public
 * validateMove()/commitWorkingCopy() are thin facades over the manager so the
 * controller's surface is unchanged.
 *
 * Both menu_link_content links and static (*.links.menu.yml) links that support
 * parent/weight overrides are moveable — see MenuLinkMovability. Writes go
 * through the menu link manager, which persists to the entity or to
 * StaticMenuLinkOverrides as appropriate, exactly as core's menu overview form
 * does. Links whose plugin can't persist the change are refused up front.
 *
 * Extension without subclassing:
 *   - hook_menu_tree_ui_pre_move_alter() — veto a move before the menu checks.
 *   - hook_menu_tree_ui_post_move() — react after a successful move.
 *
 * Uses StringTranslationTrait for the user-facing messages below; the trait's
 * getStringTranslation() falls back to \Drupal::translation() since none is
 * injected here.
 */
final class MenuTreeMover implements TreeMoveHandlerInterface {

  use StringTranslationTrait;

  /**
   * The generic reorder orchestrator.
   *
   * Stateless and dependency-free, so it is instantiated rather than injected:
   * making it a container service would force every consumer's container to
   * resolve a cross-module reference at install time for no benefit.
   */
  private readonly TreeReorderManager $reorderManager;

  /**
   * Pending re-parents to overlay on live ancestry, or NULL for live-only.
   *
   * Set only for the duration of a record-time validateMove() so the cycle and
   * depth checks see the tree the editor is looking at (live storage with the
   * menu's pending working-copy moves applied). NULL during commit, where ops
   * are replayed against live storage as they apply and the overlay must not be
   * counted on top.
   *
   * @var array<string, string>|null
   */
  private ?array $overlayEffectiveParents = NULL;

  public function __construct(
    private readonly MenuTreeStorageInterface $treeStorage,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly MenuTreeLoader $loader,
    private readonly MenuLinkManagerInterface $menuLinkManager,
    private readonly LoggerInterface $logger,
  ) {
    $this->reorderManager = new TreeReorderManager();
  }

  /**
   * Validates a move without persisting it.
   *
   * Thin facade over TreeReorderManager: the manager runs the universal
   * structural checks (self-target, cycle) and then calls back into this
   * handler's menu rules. The editing UI records a move only after this
   * passes, and Save re-runs it, so a move that became invalid after an earlier
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
    // Validate against the overlaid tree the editor sees: live storage with the
    // menu's already-pending moves applied. The move being recorded is not yet
    // in the working copy, so it is not double-counted.
    $this->overlayEffectiveParents = $this->loader->getPendingParentMap($menu_name);
    try {
      return $this->reorderManager->validateMove(
        $menu_name,
        new MoveIntent($moved_id, $new_parent_id, $before_id),
        $this,
      );
    }
    finally {
      $this->overlayEffectiveParents = NULL;
    }
  }

  /**
   * Commits a menu's pending working-copy moves to live storage.
   *
   * Thin facade over TreeReorderManager::commitWorkingCopy(); the manager owns
   * the validate-all-then-apply-in-order replay and calls back into this
   * handler to validate and write each move. Menu link writes are not safe to
   * wrap in a database transaction — the menu storage rebuild closes it — so
   * this is ordered, best-effort application, matching core's menu overview
   * form. The caller clears the working copy after a successful commit.
   *
   * @param string $menu_name
   *   The menu whose working copy to commit.
   * @param array<int, array<string, mixed>> $ops
   *   The recorded ops, in order.
   *
   * @return array{ok: bool, code?: string, message?: string, failed_op?: int, applied_ops?: array<int, int>}
   *   ['ok' => TRUE] on success, otherwise the first failing op's outcome plus
   *   the $ops indices of any moves from this batch already applied.
   */
  public function commitWorkingCopy(string $menu_name, array $ops): array {
    return $this->reorderManager->commitWorkingCopy($menu_name, $ops, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConstraints(string $tree_id, MoveIntent $intent): array {
    // Allow modules to veto the move before the menu's own checks.
    // Implementing hook_menu_tree_ui_pre_move_alter() and setting
    // $veto['veto'] = TRUE will block the move; set $veto['message'] to
    // provide a user-facing reason. alter() binds its context arguments by
    // reference, so they are passed as locals rather than readonly MoveIntent
    // properties.
    $menu_name = $tree_id;
    $moved_id = $intent->id;
    $new_parent_id = $intent->newParent;
    $before_id = $intent->beforeId;
    $veto = [];
    $this->moduleHandler->alter('menu_tree_ui_pre_move', $veto, $menu_name, $moved_id, $new_parent_id, $before_id);
    if (!empty($veto['veto'])) {
      return [
        'ok' => FALSE,
        'code' => 'vetoed',
        'message' => $veto['message'] ?? (string) $this->t('Move blocked by a module.'),
      ];
    }

    $link = $this->loadLink($intent->id);
    if ($link === NULL || !MenuLinkMovability::isMovable($link)) {
      return [
        'ok' => FALSE,
        'code' => 'not_moveable',
        'message' => (string) $this->t('This menu link cannot be reordered.'),
      ];
    }
    if ($link->getMenuName() !== $tree_id) {
      return [
        'ok' => FALSE,
        'code' => 'menu_mismatch',
        'message' => (string) $this->t('Link belongs to a different menu.'),
      ];
    }

    if ($intent->beforeId !== NULL && $intent->beforeId === $intent->id) {
      return [
        'ok' => FALSE,
        'code' => 'before_self',
        'message' => (string) $this->t('A link cannot be placed before itself.'),
      ];
    }

    // MenuTreeStorage::preSave() derives a link's menu from its parent, so a
    // move into another menu's parent would silently re-home the link if this
    // were not caught here, before the depth-cap check reads the parent's
    // root path.
    if ($intent->newParent !== '') {
      if (!$this->menuLinkManager->hasDefinition($intent->newParent)) {
        return [
          'ok' => FALSE,
          'code' => 'unknown_parent',
          'message' => (string) $this->t('The destination no longer exists.'),
        ];
      }
      $new_parent_definition = $this->menuLinkManager->getDefinition($intent->newParent);
      if ($new_parent_definition['menu_name'] !== $tree_id) {
        return [
          'ok' => FALSE,
          'code' => 'parent_menu_mismatch',
          'message' => (string) $this->t('The destination is in a different menu.'),
        ];
      }
    }

    if (!$this->fitsWithinDepthCap($intent->id, $intent->newParent)) {
      return [
        'ok' => FALSE,
        'code' => 'too_deep',
        'message' => (string) $this->t('Moving here would exceed the 9-level depth limit.'),
      ];
    }

    return ['ok' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestorIds(string $tree_id, string $parent_id): array {
    // A parent id that no longer exists (e.g. deleted by a concurrent editor)
    // has no ancestors to check against; returning an empty list here lets
    // validateConstraints() report a clean 'unknown_parent' outcome instead of
    // MenuTreeStorage::getRootPathIds() throwing on an unknown id.
    if (!$this->menuLinkManager->hasDefinition($parent_id)) {
      return [];
    }
    // During a record-time validation, walk the effective chain so a pending
    // move that relocated an ancestor is reflected; otherwise the materialized
    // path (root → … → parent) gives O(1) lookups against the menu_tree table.
    if ($this->overlayEffectiveParents !== NULL) {
      return $this->overlayAncestorChain($parent_id);
    }
    return $this->treeStorage->getRootPathIds($parent_id);
  }

  /**
   * {@inheritdoc}
   */
  public function applyMove(string $tree_id, MoveIntent $intent): void {
    $weight = $this->persistMove($tree_id, $intent->id, $intent->newParent, $intent->beforeId);
    $this->moduleHandler->invokeAll(
      'menu_tree_ui_post_move',
      [$tree_id, $intent->id, $intent->newParent, $weight],
    );
  }

  /**
   * Writes a validated move to live storage (no transaction, no validation).
   *
   * Renumbers the destination siblings first — reading their current order
   * (before the move) and re-weighting everything except the moved link, which
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
   * Loads the menu link plugin instance by plugin ID, or NULL if unknown.
   */
  private function loadLink(string $plugin_id): ?MenuLinkInterface {
    if (!$this->menuLinkManager->hasDefinition($plugin_id)) {
      return NULL;
    }
    return $this->menuLinkManager->createInstance($plugin_id);
  }

  /**
   * Checks that moved subtree height + new parent depth <= core's 9 limit.
   *
   * During a record-time validation the new parent's depth is measured on the
   * overlaid tree, so a pending move that deepened the parent is accounted for.
   */
  private function fitsWithinDepthCap(string $moved_id, string $new_parent_id): bool {
    $max = $this->treeStorage->maxDepth();
    if ($new_parent_id === '') {
      $new_parent_depth = 0;
    }
    elseif ($this->overlayEffectiveParents !== NULL) {
      $new_parent_depth = count($this->overlayAncestorChain($new_parent_id));
    }
    else {
      $new_parent_depth = count($this->treeStorage->getRootPathIds($new_parent_id));
    }
    $moved_height = $this->treeStorage->getSubtreeHeight($moved_id);
    return ($new_parent_depth + $moved_height) <= $max;
  }

  /**
   * The ancestor chain of a link, following pending moves.
   *
   * Walks from $start up to the menu root, taking each step's parent from the
   * pending overlay when the link was moved and from live storage otherwise.
   * The chain includes $start itself, matching getRootPathIds(). A visited set
   * and the depth cap guard against a malformed pending set forming a loop.
   *
   * @param string $start
   *   Plugin ID to walk up from.
   *
   * @return string[]
   *   The chain from $start to the root, $start first.
   */
  private function overlayAncestorChain(string $start): array {
    $chain = [];
    $seen = [];
    $current = $start;
    $limit = $this->treeStorage->maxDepth() + 1;
    while ($current !== '' && !isset($seen[$current]) && count($chain) < $limit) {
      $seen[$current] = TRUE;
      $chain[] = $current;
      $current = $this->effectiveParent($current);
    }
    return $chain;
  }

  /**
   * The parent of a link under the pending overlay, else its live parent.
   *
   * @param string $link_id
   *   Plugin ID whose parent to resolve.
   *
   * @return string
   *   The parent plugin ID, or '' for the menu root.
   */
  private function effectiveParent(string $link_id): string {
    if (isset($this->overlayEffectiveParents[$link_id])) {
      return $this->overlayEffectiveParents[$link_id];
    }
    $definition = $this->menuLinkManager->getDefinition($link_id, FALSE);
    return $definition === NULL ? '' : (string) ($definition['parent'] ?? '');
  }

  /**
   * Renumbers destination siblings so the moved link lands at the right spot.
   *
   * Works on the canonical sibling order the UI shows — every link type, not
   * just menu_link_content. That matters for two reasons:
   *  - $before_id may point at a static (module-defined) link such as the front
   *    page; an entity-only query would never find it, so the moved link would
   *    silently fall through to the end of the list.
   *  - Static links must be re-weighted alongside content links to keep the
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
    // and this write-side re-weight produce the same order.
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
          $this->logger->warning('Could not re-weight menu link %id while saving menu %menu; its backing entity is missing. %message', [
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
