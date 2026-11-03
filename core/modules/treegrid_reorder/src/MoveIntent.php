<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder;

/**
 * A request to move one tree item to a new parent and position.
 *
 * The same shape the client POSTs ({id, new_parent, before_id}) and that the
 * working copy records, carried across the reorder seam so the manager and the
 * consumer's move handler share one vocabulary.
 *
 * @api
 */
final readonly class MoveIntent {

  /**
   * @param string $id
   *   Id of the item being moved.
   * @param string $newParent
   *   Id of the new parent, or '' for the tree root.
   * @param string|null $beforeId
   *   Id of the sibling the moved item should sit before, or NULL for the end.
   */
  public function __construct(
    public string $id,
    public string $newParent,
    public ?string $beforeId,
  ) {}

}
