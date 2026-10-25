<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * Replays a working copy's recorded moves on lists of ids.
 *
 * Works on plain id lists so a consumer can apply the recorded moves to one
 * level's ids without loading the links. A parent id is the consumer's node id
 * and '' means the top of the tree.
 *
 * @api
 */
final class MoveLogReplay {

  /**
   * Collapses move ops into a map of moved id to its pending parent.
   *
   * The last move of an id wins. Ops whose type isn't 'move' are ignored.
   *
   * @param array<int, array<string, mixed>> $ops
   *   The ops, in recorded order.
   *
   * @return array<string, string>
   *   Moved id => pending parent id ('' for the top).
   */
  public function pendingParents(array $ops): array {
    $map = [];
    foreach ($ops as $op) {
      if (($op['type'] ?? '') === 'move') {
        $moved = (string) ($op['id'] ?? '');
        if ($moved !== '') {
          $map[$moved] = (string) ($op['new_parent'] ?? '');
        }
      }
    }
    return $map;
  }

  /**
   * Puts an id in front of another in an ordered list, or at the end.
   *
   * The moved id is removed first if present. It goes at the end when
   * $before_id is NULL or not in the list.
   *
   * @param list<string> $order
   *   The ordered ids.
   * @param string $moved
   *   The id to place.
   * @param string|null $before_id
   *   The id to place it in front of.
   *
   * @return list<string>
   *   The new order.
   */
  public function spliceBefore(array $order, string $moved, ?string $before_id): array {
    $order = array_values(array_filter($order, static fn(string $id): bool => $id !== $moved));
    $out = [];
    $inserted = FALSE;
    foreach ($order as $id) {
      if (!$inserted && $before_id !== NULL && $id === $before_id) {
        $out[] = $moved;
        $inserted = TRUE;
      }
      $out[] = $id;
    }
    if (!$inserted) {
      $out[] = $moved;
    }
    return $out;
  }

  /**
   * Replays the moves on one level's ids.
   *
   * For each move in order: when its new parent is this level and the id is in
   * the list or in $existing_ids, the id is spliced in front of its anchor. A
   * move into this level of an id that is neither is skipped, as if never
   * recorded, so a later move anchored on it lands at the end. A move of a
   * listed id to another level removes it.
   *
   * @param string $parent_id
   *   The level's parent id ('' for the top).
   * @param list<string> $live_ids
   *   The level's live ids, in order.
   * @param array<int, array<string, mixed>> $ops
   *   The ops, in recorded order.
   * @param list<string> $existing_ids
   *   The moved ids that still exist.
   *
   * @return list<string>
   *   The ids the editor sees in the level, in order.
   */
  public function replayLevel(string $parent_id, array $live_ids, array $ops, array $existing_ids): array {
    $order = $live_ids;
    foreach ($ops as $op) {
      if (($op['type'] ?? '') !== 'move') {
        continue;
      }
      $moved = (string) ($op['id'] ?? '');
      $before_id = $op['before_id'] ?? NULL;
      $before_id = $before_id === NULL ? NULL : (string) $before_id;
      $in_list = in_array($moved, $order, TRUE);

      if ((string) ($op['new_parent'] ?? '') === $parent_id) {
        if ($in_list || in_array($moved, $existing_ids, TRUE)) {
          $order = $this->spliceBefore($order, $moved, $before_id);
        }
      }
      elseif ($in_list) {
        $order = array_values(array_filter($order, static fn(string $id): bool => $id !== $moved));
      }
    }
    return $order;
  }

}
