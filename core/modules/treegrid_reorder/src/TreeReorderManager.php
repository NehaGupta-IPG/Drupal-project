<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * Orchestrates a tree move: universal validation and the commit replay.
 *
 * The reusable half of reordering. It enforces the two structural rules that
 * hold for every tree — you cannot drop an item onto itself, nor into one of
 * its own descendants — and runs the Save-time commit: validate the whole batch
 * up front, then apply each move in order, re-validating immediately before its
 * write so a move made invalid by an earlier one (a newly formed cycle) is
 * caught. Everything domain-specific (movability, scope, depth, ancestry, the
 * write) is delegated to a TreeMoveHandlerInterface, so this class never knows
 * how a particular tree is stored.
 *
 * @api
 */
final class TreeReorderManager {

  /**
   * Validates a single move: the universal checks, then the domain rules.
   *
   * @param string $tree_id
   *   The tree the move happens within.
   * @param \Drupal\treegrid_reorder\MoveIntent $intent
   *   The requested move.
   * @param \Drupal\treegrid_reorder\TreeMoveHandlerInterface $handler
   *   The consumer's move handler.
   *
   * @return array{ok: bool, code?: string, message?: string}
   *   ['ok' => TRUE] when the move is allowed, otherwise an error outcome.
   */
  public function validateMove(string $tree_id, MoveIntent $intent, TreeMoveHandlerInterface $handler): array {
    if ($intent->newParent === $intent->id) {
      return ['ok' => FALSE, 'code' => 'self_target', 'message' => 'Cannot drop an item onto itself.'];
    }

    if ($intent->newParent !== '' && in_array($intent->id, $handler->getAncestorIds($tree_id, $intent->newParent), TRUE)) {
      return ['ok' => FALSE, 'code' => 'cycle', 'message' => 'Cannot drop an item into one of its own descendants.'];
    }

    return $handler->validateConstraints($tree_id, $intent);
  }

  /**
   * Commits a batch of pending working-copy moves to storage.
   *
   * Validates every recorded move first, so a batch with an individually
   * invalid move aborts before anything is written, then applies the moves in
   * order, re-validating each against the now-current state. Non-move ops are
   * ignored. The caller clears the working copy after a successful commit.
   *
   * @param string $tree_id
   *   The tree whose working copy to commit.
   * @param array<int, array<string, mixed>> $ops
   *   The recorded ops, in order.
   * @param \Drupal\treegrid_reorder\TreeMoveHandlerInterface $handler
   *   The consumer's move handler.
   *
   * @return array{ok: bool, code?: string, message?: string, failed_op?: int}
   *   ['ok' => TRUE] on success, otherwise the first failing op's outcome.
   */
  public function commitWorkingCopy(string $tree_id, array $ops, TreeMoveHandlerInterface $handler): array {
    $moves = array_values(array_filter(
      $ops,
      static fn(array $op): bool => ($op['type'] ?? '') === 'move',
    ));
    if ($moves === []) {
      return ['ok' => TRUE];
    }

    $intents = array_map(static function (array $op): MoveIntent {
      $before_id = $op['before_id'] ?? NULL;
      return new MoveIntent(
        (string) ($op['id'] ?? ''),
        (string) ($op['new_parent'] ?? ''),
        $before_id === NULL ? NULL : (string) $before_id,
      );
    }, $moves);

    // Pre-flight: reject the batch if any op is individually invalid, before
    // writing anything.
    foreach ($intents as $index => $intent) {
      $validation = $this->validateMove($tree_id, $intent, $handler);
      if (!$validation['ok']) {
        return $this->commitFailure($validation, $index);
      }
    }

    // Apply in order, re-validating against the now-current state.
    foreach ($intents as $index => $intent) {
      $validation = $this->validateMove($tree_id, $intent, $handler);
      if (!$validation['ok']) {
        return $this->commitFailure($validation, $index);
      }
      $handler->applyMove($tree_id, $intent);
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

}
