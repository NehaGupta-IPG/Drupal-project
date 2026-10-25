<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * The domain seam for reordering a particular kind of tree.
 *
 * TreeReorderManager owns the universal parts of a move — the self-target and
 * cycle checks, and the validate-then-apply commit loop — and delegates
 * everything domain-specific to an implementation of this interface. A consumer
 * module implements it to say which items may move, what local rules apply
 * (such as a depth cap), how ancestry is resolved, and how a move is written.
 * Nothing here knows about the manager's orchestration, and the
 * manager knows nothing about the consumer's storage — they meet only at this
 * contract.
 *
 * @api
 */
interface TreeMoveHandlerInterface {

  /**
   * Validates a move against the consumer's domain rules.
   *
   * Called by TreeReorderManager after its universal structural checks
   * (self-target, cycle) pass. Implementations enforce their own rules here —
   * which items are movable, ownership/scope, depth limits, vetoes — and must
   * NOT re-implement the structural checks.
   *
   * An error's message is safe HTML, escaped by the implementation (cast a
   * t() string with @ placeholders), because the client announces and shows it
   * without escaping.
   *
   * @param string $tree_id
   *   The tree the move happens within.
   * @param \Drupal\treegrid_reorder\MoveIntent $intent
   *   The requested move.
   *
   * @return array{ok: bool, code?: string, message?: string}
   *   ['ok' => TRUE] when the move is allowed, otherwise an error outcome.
   */
  public function validateConstraints(string $tree_id, MoveIntent $intent): array;

  /**
   * Returns the ancestor item ids of $parent_id, for the manager's cycle check.
   *
   * The manager rejects a move whose item appears among the new parent's
   * ancestors. Implementations should return the parent's root path (the chain
   * from the tree root down to and including $parent_id).
   *
   * @param string $tree_id
   *   The tree the move happens within.
   * @param string $parent_id
   *   The prospective new parent id (never '' — the manager skips the cycle
   *   check for a move to the root).
   *
   * @return string[]
   *   The ancestor ids.
   */
  public function getAncestorIds(string $tree_id, string $parent_id): array;

  /**
   * Writes a validated move to storage and fires any domain reactions.
   *
   * Called by TreeReorderManager only after the move has just re-validated.
   * Implementations persist the new parent/position and may invoke their own
   * post-move hooks. The caller owns transaction boundaries.
   *
   * @param string $tree_id
   *   The tree the move happens within.
   * @param \Drupal\treegrid_reorder\MoveIntent $intent
   *   The validated move to apply.
   */
  public function applyMove(string $tree_id, MoveIntent $intent): void;

}
