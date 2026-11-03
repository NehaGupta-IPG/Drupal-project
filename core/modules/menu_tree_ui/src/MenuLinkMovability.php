<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\MenuLinkInterface;

/**
 * Decides whether a menu link can be reordered or re-parented in the tree UI.
 *
 * The UI lets a link be dragged (or keyboard-reordered) only when a parent +
 * weight change will actually persist:
 *  - menu_link_content links store parent and weight on the entity;
 *  - MenuLinkDefault — the default class for *.links.menu.yml links such as the
 *    front page — persists parent and weight as overrides via
 *    StaticMenuLinkOverrides (the same mechanism core's menu overview form
 *    relies on).
 *
 * Links backed by a bespoke plugin class that does not support those overrides
 * stay locked, so the UI never implies a move it cannot honor.
 *
 * @internal
 */
final class MenuLinkMovability {

  /**
   * Whether the given link can be moved within the tree UI.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The menu link plugin instance.
   *
   * @return bool
   *   TRUE if the link's parent and weight changes will persist.
   */
  public static function isMovable(MenuLinkInterface $link): bool {
    return MenuTreeUiConstants::isContentLink($link->getPluginId())
      || $link instanceof MenuLinkDefault;
  }

}
