<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder_test;

use Drupal\Core\State\StateInterface;
use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeMoveHandlerInterface;

/**
 * A move handler for the nested tree that backs the reorder tests.
 *
 * The tree is an ordered list of items kept in State. Each item has an id, a
 * parent id ('' for the top level) and a movable flag (TRUE, FALSE, or NULL for
 * "no opinion"). The order of the list gives the order among siblings, so a
 * level is the items with that parent, in list order. applyMove() rewrites the
 * list, which the page and children endpoints read back.
 */
final class TestTreeMoveHandler implements TreeMoveHandlerInterface {

  /**
   * The State key holding the tree.
   */
  public const STATE_KEY = 'treegrid_reorder_test.tree';

  /**
   * The tree when nothing has been moved yet.
   */
  public const DEFAULT_TREE = [
    ['id' => '1', 'parent' => '', 'movable' => TRUE],
    ['id' => '2', 'parent' => '', 'movable' => TRUE],
    ['id' => '2a', 'parent' => '2', 'movable' => TRUE],
    ['id' => '2b', 'parent' => '2', 'movable' => TRUE],
    ['id' => '2b1', 'parent' => '2b', 'movable' => TRUE],
    ['id' => '2b2', 'parent' => '2b', 'movable' => TRUE],
    ['id' => '2c', 'parent' => '2', 'movable' => TRUE],
    ['id' => '3', 'parent' => '', 'movable' => TRUE],
    ['id' => '3a', 'parent' => '3', 'movable' => TRUE],
    ['id' => '3b', 'parent' => '3', 'movable' => FALSE],
    ['id' => '3c', 'parent' => '3', 'movable' => NULL],
    ['id' => '4', 'parent' => '', 'movable' => TRUE],
  ];

  public function __construct(
    private readonly StateInterface $state,
  ) {}

  /**
   * The whole tree as an ordered list of items.
   *
   * @return array<int, array{id: string, parent: string, movable: ?bool}>
   *   The items.
   */
  public function items(): array {
    return $this->state->get(self::STATE_KEY, self::DEFAULT_TREE);
  }

  /**
   * The ids of one level, in order.
   *
   * @param string $parent_id
   *   The parent id, or '' for the top level.
   *
   * @return string[]
   *   The ids.
   */
  public function levelIds(string $parent_id): array {
    $ids = [];
    foreach ($this->items() as $item) {
      if ($item['parent'] === $parent_id) {
        $ids[] = $item['id'];
      }
    }
    return $ids;
  }

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
    $parents = [];
    foreach ($this->items() as $item) {
      $parents[$item['id']] = $item['parent'];
    }
    $ancestors = [];
    $current = $parent_id;
    while ($current !== '' && isset($parents[$current]) && !in_array($current, $ancestors, TRUE)) {
      $ancestors[] = $current;
      $current = $parents[$current];
    }
    return array_reverse($ancestors);
  }

  /**
   * {@inheritdoc}
   */
  public function applyMove(string $tree_id, MoveIntent $intent): void {
    $moved = NULL;
    $rest = [];
    foreach ($this->items() as $item) {
      if ($item['id'] === $intent->id) {
        $moved = $item;
      }
      else {
        $rest[] = $item;
      }
    }
    if ($moved === NULL) {
      return;
    }
    $moved['parent'] = $intent->newParent;

    $position = count($rest);
    if ($intent->beforeId !== NULL) {
      foreach ($rest as $index => $item) {
        if ($item['id'] === $intent->beforeId && $item['parent'] === $intent->newParent) {
          $position = $index;
          break;
        }
      }
    }
    array_splice($rest, $position, 0, [$moved]);
    $this->state->set(self::STATE_KEY, $rest);
  }

}
