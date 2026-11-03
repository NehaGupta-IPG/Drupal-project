<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Unit;

use Drupal\treegrid_reorder\MoveIntent;
use Drupal\treegrid_reorder\TreeMoveHandlerInterface;

/**
 * A move handler that returns set outcomes and records the moves applied.
 */
final class RecordingTreeMoveHandler implements TreeMoveHandlerInterface {

  /**
   * Ancestor ids returned for the cycle check.
   *
   * @var string[]
   */
  public array $ancestors = [];

  /**
   * The domain validation outcome to return.
   *
   * @var array<string, mixed>
   */
  public array $constraintResult = ['ok' => TRUE];

  /**
   * Ids of moves applied, in order.
   *
   * @var string[]
   */
  public array $applied = [];

  /**
   * {@inheritdoc}
   */
  public function validateConstraints(string $tree_id, MoveIntent $intent): array {
    return $this->constraintResult;
  }

  /**
   * {@inheritdoc}
   */
  public function getAncestorIds(string $tree_id, string $parent_id): array {
    return $this->ancestors;
  }

  /**
   * {@inheritdoc}
   */
  public function applyMove(string $tree_id, MoveIntent $intent): void {
    $this->applied[] = $intent->id;
  }

}
