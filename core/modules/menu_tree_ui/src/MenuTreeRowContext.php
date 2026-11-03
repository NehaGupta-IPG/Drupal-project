<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui;

use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * The per-row context menu_tree_ui hands to its TreegridColumn plugins.
 *
 * TreegridColumnInterface::buildCell() receives a consumer-defined row
 * context (typed as a generic `object`). menu_tree_ui's columns need the tree
 * element, and the operations column also needs the row's content entity —
 * which the controller batch-loads once per level so columns never query per
 * row. This bundles the two; $entity is NULL for static/yml-defined links.
 */
final readonly class MenuTreeRowContext {

  public function __construct(
    public MenuLinkTreeElement $element,
    public ?MenuLinkContentInterface $entity,
  ) {}

}
