<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\Lock;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * A per-tree working copy of pending reorder operations.
 *
 * Committing each move immediately leaves an editor no way to back out. The
 * working copy restores Save/cancel by recording moves in the shared tempstore
 * instead of writing them, and replaying them on Save (see TreeReorderManager).
 *
 * A full snapshot of the tree's mutable state would defeat the lazy-load design
 * these UIs exist for: a large tree would be expensive to snapshot and hold.
 * Instead this stores a bounded, ordered list of operations (one per move) that
 * the consumer overlays at read time onto each lazily-loaded level. The cost is
 * proportional to the number of edits, not the size of the tree.
 *
 * Uses the shared (not private) tempstore so its owner/lock metadata can warn
 * when a second user is already editing the same tree — the model Views UI and
 * Layout Builder use.
 *
 * Each consumer owns a tempstore collection (passed at construction) and keys
 * entries within it by a tree id (e.g. a menu name); collections keep two
 * consumers' ids from colliding.
 */
final class TreegridWorkingCopy {

  /**
   * Per-request cache of decoded op lists, keyed by tree id.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  private array $cache = [];

  public function __construct(
    private readonly SharedTempStoreFactory $tempStoreFactory,
    private readonly AccountInterface $currentUser,
    private readonly string $collection,
  ) {}

  /**
   * Returns the pending operations for a tree, in the order they were recorded.
   *
   * @param string $id
   *   The tree id.
   *
   * @return array<int, array<string, mixed>>
   *   The ordered op list; empty when there are no pending changes.
   */
  public function getOps(string $id): array {
    if (isset($this->cache[$id])) {
      return $this->cache[$id];
    }
    $stored = $this->store()->get($id);
    $ops = is_array($stored['ops'] ?? NULL) ? $stored['ops'] : [];
    $this->cache[$id] = $ops;
    return $ops;
  }

  /**
   * Appends a pending operation to a tree's working copy.
   *
   * @param string $id
   *   The tree id.
   * @param array<string, mixed> $op
   *   The operation to record (e.g. ['type' => 'move', 'id' => …]).
   *
   * @return bool
   *   TRUE if recorded; FALSE when another user holds the working copy.
   */
  public function addOp(string $id, array $op): bool {
    if ($this->isLocked($id)) {
      return FALSE;
    }
    $ops = $this->getOps($id);
    $ops[] = $op;
    $this->store()->set($id, ['ops' => $ops]);
    $this->cache[$id] = $ops;
    return TRUE;
  }

  /**
   * Whether a tree has any pending changes.
   *
   * @param string $id
   *   The tree id.
   *
   * @return bool
   *   TRUE when there is at least one pending op.
   */
  public function hasChanges(string $id): bool {
    return $this->getOps($id) !== [];
  }

  /**
   * Discards a tree's working copy.
   *
   * @param string $id
   *   The tree id.
   */
  public function clear(string $id): void {
    $this->store()->delete($id);
    unset($this->cache[$id]);
  }

  /**
   * Whether the working copy is held by a different user.
   *
   * @param string $id
   *   The tree id.
   *
   * @return bool
   *   TRUE when a working copy exists and its owner is not the current user.
   */
  public function isLocked(string $id): bool {
    $lock = $this->getLockMetadata($id);
    return $lock !== NULL && (string) $lock->getOwnerId() !== (string) $this->currentUser->id();
  }

  /**
   * Returns the working copy's lock metadata, or NULL when none exists.
   *
   * @param string $id
   *   The tree id.
   *
   * @return \Drupal\Core\TempStore\Lock|null
   *   The lock (owner + updated time), or NULL.
   */
  public function getLockMetadata(string $id): ?Lock {
    return $this->store()->getMetadata($id);
  }

  /**
   * Returns the shared tempstore for this consumer's collection.
   */
  private function store(): SharedTempStore {
    return $this->tempStoreFactory->get($this->collection);
  }

}
