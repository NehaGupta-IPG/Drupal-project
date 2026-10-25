<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * The working copy view backed by the recorded moves.
 *
 * @internal
 *   Consumers get it from TreegridWorkingCopy::view().
 */
final class LogWorkingCopyView implements WorkingCopyViewInterface {

  /**
   * Moved id => pending parent id, worked out on first use.
   *
   * @var array<string, string>|null
   */
  private ?array $pendingParents = NULL;

  /**
   * Constructs the view.
   *
   * @param array<int, array<string, mixed>> $ops
   *   The recorded ops, in order.
   * @param \Drupal\treegrid_reorder\MoveLogReplay $replay
   *   The replay helper.
   */
  public function __construct(
    private readonly array $ops,
    private readonly MoveLogReplay $replay,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function levelIds(string $parent_id, array $live_ids, array $existing_ids): array {
    return $this->replay->replayLevel($parent_id, $live_ids, $this->ops, $existing_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function pendingParent(string $id): ?string {
    return $this->pendingParents()[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function pendingIds(): array {
    return array_map('strval', array_keys($this->pendingParents()));
  }

  /**
   * Returns the pending parent of each moved id.
   *
   * @return array<string, string>
   *   Moved id => pending parent id.
   */
  private function pendingParents(): array {
    return $this->pendingParents ??= $this->replay->pendingParents($this->ops);
  }

}
