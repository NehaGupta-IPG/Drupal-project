<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Defines an interface for contextual menu link tree manipulators.
 */
interface MenuLinkTreeContextualManipulatorInterface extends CacheableDependencyInterface {

  /**
   * Whether this menu link tree manipulator applies to the current context.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree.
   * @param mixed $context
   *   Context related to the menu link tree being manipulated. This is usually
   *   the object triggering the menu link tree transformation.
   *
   * @return bool
   *   TRUE if this menu link tree manipulator should be used.
   */
  public function applies(array $tree, mixed $context): bool;

  /**
   * Process a menu link tree manipulator.
   *
   * The menu link tree manipulator can be used for transforming the menu links.
   * The manipulator needs to take into account that the menu links may contain
   * cacheable metadata. Removing items directly leads into loss of cacheable
   * metadata. Instead, the links should be converted into inaccessible links.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   * @param mixed $context
   *   Context related to the menu link tree being manipulated. This is usually
   *   the object triggering the menu link tree transformation.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated link tree.
   */
  public function process(array $tree, mixed $context): array;

}
