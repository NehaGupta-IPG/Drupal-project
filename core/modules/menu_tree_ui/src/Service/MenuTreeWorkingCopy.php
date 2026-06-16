<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\Lock;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * A per-menu working copy of pending reorder operations.
 *
 * The classic menu admin form lets an editor make many changes and then Save
 * or cancel. This module commits each move immediately, which leaves no way to
 * back out. The working copy restores Save/cancel by recording moves in the
 * shared tempstore instead of writing them, and replaying them on Save (see
 * MenuTreeMover::commitWorkingCopy()).
 *
 * A full snapshot of the tree's mutable state would defeat the lazy-load design
 * this module exists for: a large menu would be expensive to snapshot and hold.
 * Instead this stores a bounded, ordered list of operations (one per move), and
 * MenuTreeLoader overlays them at read time onto each lazily-loaded level. The
 * cost is proportional to the number of edits, not the size of the menu.
 *
 * Uses the shared (not private) tempstore so its owner/lock metadata can warn
 * when a second user is already editing the same menu — the model Views UI and
 * Layout Builder use.
 */
final class MenuTreeWorkingCopy {

  /**
   * The tempstore collection name.
   */
  private const COLLECTION = 'menu_tree_ui.working_copy';

  /**
   * Per-request cache of decoded op lists, keyed by menu name.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  private array $cache = [];

  public function __construct(
    private readonly SharedTempStoreFactory $tempStoreFactory,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Returns the pending operations for a menu, in the order they were recorded.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return array<int, array<string, mixed>>
   *   The ordered op list; empty when there are no pending changes.
   */
  public function getOps(string $menu_name): array {
    if (isset($this->cache[$menu_name])) {
      return $this->cache[$menu_name];
    }
    $stored = $this->store()->get($menu_name);
    $ops = is_array($stored['ops'] ?? NULL) ? $stored['ops'] : [];
    $this->cache[$menu_name] = $ops;
    return $ops;
  }

  /**
   * Appends a pending operation to a menu's working copy.
   *
   * @param string $menu_name
   *   The menu entity ID.
   * @param array<string, mixed> $op
   *   The operation to record (e.g. ['type' => 'move', 'id' => …]).
   *
   * @return bool
   *   TRUE if recorded; FALSE when another user holds the working copy.
   */
  public function addOp(string $menu_name, array $op): bool {
    if ($this->isLocked($menu_name)) {
      return FALSE;
    }
    $ops = $this->getOps($menu_name);
    $ops[] = $op;
    $this->store()->set($menu_name, ['ops' => $ops]);
    $this->cache[$menu_name] = $ops;
    return TRUE;
  }

  /**
   * Whether a menu has any pending changes.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return bool
   *   TRUE when there is at least one pending op.
   */
  public function hasChanges(string $menu_name): bool {
    return $this->getOps($menu_name) !== [];
  }

  /**
   * Discards a menu's working copy.
   *
   * @param string $menu_name
   *   The menu entity ID.
   */
  public function clear(string $menu_name): void {
    $this->store()->delete($menu_name);
    unset($this->cache[$menu_name]);
  }

  /**
   * Whether the working copy is held by a different user.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return bool
   *   TRUE when a working copy exists and its owner is not the current user.
   */
  public function isLocked(string $menu_name): bool {
    $lock = $this->getLockMetadata($menu_name);
    return $lock !== NULL && (string) $lock->getOwnerId() !== (string) $this->currentUser->id();
  }

  /**
   * Returns the working copy's lock metadata, or NULL when none exists.
   *
   * @param string $menu_name
   *   The menu entity ID.
   *
   * @return \Drupal\Core\TempStore\Lock|null
   *   The lock (owner + updated time), or NULL.
   */
  public function getLockMetadata(string $menu_name): ?Lock {
    return $this->store()->getMetadata($menu_name);
  }

  /**
   * Returns the shared tempstore for this module's collection.
   */
  private function store(): SharedTempStore {
    return $this->tempStoreFactory->get(self::COLLECTION);
  }

}
