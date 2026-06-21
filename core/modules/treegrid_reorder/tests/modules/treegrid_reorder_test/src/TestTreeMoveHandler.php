<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder_test;

use Drupal\Core\State\StateInterface;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeMoveHandlerInterface;

/**
 * A flat-tree move handler backing the reorder gesture test.
 *
 * The "tree" is a single flat list of item IDs kept in State, so there is no
 * ancestry and no domain constraint to enforce — the universal self-target and
 * cycle checks in TreeReorderManager are all that gate a move. applyMove()
 * rewrites the ordered list, which the page and children endpoints read back.
 */
final class TestTreeMoveHandler implements TreeMoveHandlerInterface {

  /**
   * The State key holding the ordered list of item IDs.
   */
  public const STATE_KEY = 'treegrid_reorder_test.order';

  /**
   * The default ordered list when nothing has been moved yet.
   */
  public const DEFAULT_ORDER = ['1', '2', '3', '4'];

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validateConstraints(string $tree_id, MoveIntent $intent): array {
    return ['ok' => TRUE];
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestorIds(string $tree_id, string $parent_id): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function applyMove(string $tree_id, MoveIntent $intent): void {
    $order = $this->state->get(self::STATE_KEY, self::DEFAULT_ORDER);
    $order = array_values(array_filter(
      $order,
      static fn(string $id): bool => $id !== $intent->id,
    ));

    if ($intent->beforeId === NULL) {
      $order[] = $intent->id;
    }
    else {
      $pos = array_search($intent->beforeId, $order, TRUE);
      if ($pos === FALSE) {
        $order[] = $intent->id;
      }
      else {
        array_splice($order, $pos, 0, [$intent->id]);
      }
    }

    $this->state->set(self::STATE_KEY, $order);
  }

}
