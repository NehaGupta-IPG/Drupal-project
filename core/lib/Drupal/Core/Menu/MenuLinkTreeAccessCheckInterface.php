<?php

declare(strict_types=1);

namespace Drupal\Core\Menu;

/**
 * Defines an interface for menu link tree access checking.
 *
 * To override access decisions for menu trees, decorate or replace the
 * 'menu.tree_access_check' service.
 */
interface MenuLinkTreeAccessCheckInterface {

  /**
   * Applies access checks to a menu link tree.
   *
   * Sets the 'access' property on each menu link tree element. Elements that
   * already have an access value set are left untouched, allowing callers or
   * decorating implementations to short-circuit the default check.
   *
   * Inaccessible subtrees are removed, except the top-level inaccessible link;
   * this keeps the cacheability metadata bubbling correctly.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to access-check.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The access-checked menu link tree.
   */
  public function checkAccess(array $tree): array;

}
