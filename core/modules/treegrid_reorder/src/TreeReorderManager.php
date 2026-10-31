<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Orchestrates a tree move: universal validation and the commit replay.
 *
 * The reusable half of reordering. It enforces the two structural rules that
 * hold for every tree: you cannot drop an item onto itself, nor into one of
 * its own descendants. It also replays a batch of recorded moves in order,
 * validating each against the current state immediately before its write.
 * Everything domain-specific (movability, scope, depth, ancestry, the write)
 * is delegated to a TreeMoveHandlerInterface, so this class never knows how a
 * particular tree is stored.
 *
 * Instantiated directly rather than injected (see the constructor note on the
 * consuming move handler), so it has no constructor-injected translation
 * service; StringTranslationTrait::getStringTranslation() falls back to
 * \Drupal::translation() in that case, which is acceptable here since the
 * messages below are only ever reached from within a running Drupal request.
 *
 * @api
 */
final class TreeReorderManager {

  use StringTranslationTrait;

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
   * @return array{ok: bool, code?: string, message?: \Drupal\Component\Render\MarkupInterface}
   *   ['ok' => TRUE] when the move is allowed, otherwise an error outcome.
   */
  public function validateMove(string $tree_id, MoveIntent $intent, TreeMoveHandlerInterface $handler): array {
    if ($intent->newParent === $intent->id) {
      return [
        'ok' => FALSE,
        'code' => 'self_target',
        'message' => $this->t('Cannot drop an item onto itself.'),
      ];
    }

    if ($intent->newParent !== '' && in_array($intent->id, $handler->getAncestorIds($tree_id, $intent->newParent), TRUE)) {
      return [
        'ok' => FALSE,
        'code' => 'cycle',
        'message' => $this->t('Cannot drop an item into one of its own descendants.'),
      ];
    }

    return $handler->validateConstraints($tree_id, $intent);
  }

  /**
   * Commits a batch of pending working-copy moves to storage.
   *
   * Applies the moves in order, validating each against the current state
   * immediately before its write. Non-move ops are ignored. The caller clears
   * the working copy after a successful commit.
   *
   * The batch is not validated up front, so a move that fails partway through
   * leaves the earlier moves written. A handler that wants nothing written
   * when any move is invalid validates the sequence before calling this. On
   * the first failure this returns 'failed_op' and the 'applied_ops' already
   * written, so the caller can reconcile the working copy with storage.
   *
   * @param string $tree_id
   *   The tree whose working copy to commit.
   * @param array<int, array<string, mixed>> $ops
   *   The recorded ops, in order.
   * @param \Drupal\treegrid_reorder\TreeMoveHandlerInterface $handler
   *   The consumer's move handler.
   *
   * @return array{ok: bool, code?: string, message?: \Drupal\Component\Render\MarkupInterface, failed_op?: int, applied_ops?: array<int, int>}
   *   ['ok' => TRUE] on success, otherwise the first failing op's outcome plus
   *   the $ops indices of any moves from this batch already applied before the
   *   failure — the caller must drop those from the working copy so a retry
   *   does not replay them.
   */
  public function commitWorkingCopy(string $tree_id, array $ops, TreeMoveHandlerInterface $handler): array {
    $move_indices = [];
    foreach ($ops as $index => $op) {
      if (($op['type'] ?? '') === 'move') {
        $move_indices[] = $index;
      }
    }
    if ($move_indices === []) {
      return ['ok' => TRUE];
    }

    $intents = array_map(static function (int $index) use ($ops): MoveIntent {
      $op = $ops[$index];
      $before_id = $op['before_id'] ?? NULL;
      return new MoveIntent(
        (string) ($op['id'] ?? ''),
        (string) ($op['new_parent'] ?? ''),
        $before_id === NULL ? NULL : (string) $before_id,
      );
    }, $move_indices);

    $applied = [];
    foreach ($intents as $i => $intent) {
      $validation = $this->validateMove($tree_id, $intent, $handler);
      if (!$validation['ok']) {
        return $this->commitFailure($validation, $move_indices[$i], $applied);
      }
      $handler->applyMove($tree_id, $intent);
      $applied[] = $move_indices[$i];
    }

    return ['ok' => TRUE];
  }

  /**
   * Shapes a failed-op outcome for commitWorkingCopy().
   *
   * @param array{code?: string, message?: \Drupal\Component\Render\MarkupInterface} $validation
   *   The failing validateMove() result.
   * @param int $index
   *   The $ops index of the failing move.
   * @param array<int, int> $applied
   *   The $ops indices of moves from this batch already applied.
   *
   * @return array{ok: false, code: string, message: \Drupal\Component\Render\MarkupInterface, failed_op: int, applied_ops: array<int, int>}
   *   The commit failure outcome.
   */
  private function commitFailure(array $validation, int $index, array $applied = []): array {
    return [
      'ok' => FALSE,
      'code' => $validation['code'] ?? 'commit_failed',
      'message' => $validation['message'] ?? $this->t('A pending change could not be saved.'),
      'failed_op' => $index,
      'applied_ops' => $applied,
    ];
  }

}
