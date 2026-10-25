<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * What an editor sees of a tree once the pending moves are applied.
 *
 * A parent id is the consumer's node id and '' means the top of the tree.
 *
 * @api
 */
interface WorkingCopyViewInterface {

  /**
   * Returns the ids the editor sees in a level, in order.
   *
   * @param string $parent_id
   *   The level's parent id ('' for the top).
   * @param list<string> $live_ids
   *   The level's live ids, in order.
   * @param list<string> $existing_ids
   *   The moved ids that still exist.
   *
   * @return list<string>
   *   The ids in the level, including ids moved in from other levels.
   */
  public function levelIds(string $parent_id, array $live_ids, array $existing_ids): array;

  /**
   * Returns the parent the editor sees for a link with a pending move.
   *
   * @param string $id
   *   The link id.
   *
   * @return string|null
   *   The parent id ('' for the top), or NULL when the link has no pending
   *   move and the caller should use its live parent.
   */
  public function pendingParent(string $id): ?string;

  /**
   * Returns the ids of every link with a pending move.
   *
   * @return list<string>
   *   The ids, each once.
   */
  public function pendingIds(): array;

}
