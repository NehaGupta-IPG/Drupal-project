<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui;

/**
 * A slice of one menu level as the editor sees it.
 *
 * @internal
 */
final class LevelSlice {

  /**
   * Constructs a level slice.
   *
   * @param list<string> $ids
   *   The ids of the whole level, in order, with the pending moves applied.
   * @param list<\Drupal\Core\Menu\MenuLinkTreeElement> $elements
   *   The elements of the slice, in order.
   * @param int $total
   *   The number of ids in the whole level.
   * @param string|null $nextId
   *   The id right after the slice, or NULL when the slice reaches the end of
   *   the level.
   */
  public function __construct(
    public readonly array $ids,
    public readonly array $elements,
    public readonly int $total,
    public readonly ?string $nextId,
  ) {}

}
