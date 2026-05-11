<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a couple of menu link tree manipulators.
 *
 * This class provides menu link tree manipulators to:
 * - perform render cached menu-optimized access checking
 * - optimized node access checking
 * - generate a unique index for the elements in a tree and sorting by it
 * - flatten a tree (i.e. a 1-dimensional tree)
 */
class DefaultMenuLinkTreeManipulators {

  /**
   * Constructs a \Drupal\Core\Menu\DefaultMenuLinkTreeManipulators object.
   *
   * @param \Drupal\Core\Access\AccessManagerInterface $accessManager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected AccessManagerInterface $accessManager,
    protected AccountInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Performs access checks of a menu tree.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The access-checked menu link tree.
   *
   * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Call the
   *   'menu.tree_access_check' service, or use
   *   \Drupal\Core\Menu\MenuLinkTreeInterface::checkAccess(). The batched
   *   node-link access optimization that previously required calling
   *   ::checkNodeAccess() first is now applied automatically.
   *
   * @see https://www.drupal.org/node/9999999
   */
  public function checkAccess(array $tree) {
    return \Drupal::service('menu.tree_access_check')->checkAccess($tree);
  }

  /**
   * Performs access checking for nodes in an optimized way.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   *
   * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The
   *   batched node-link access optimization is now applied automatically by
   *   the 'menu.tree_access_check' service; call its ::checkAccess() (or
   *   \Drupal\Core\Menu\MenuLinkTreeInterface::checkAccess()) instead.
   *
   * @see https://www.drupal.org/node/9999999
   */
  public function checkNodeAccess(array $tree) {
    return \Drupal::service('menu.tree_access_check')->checkAccess($tree);
  }

  /**
   * Generates a unique index and sorts by it.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function generateIndexAndSort(array $tree) {
    $new_tree = [];
    foreach ($tree as $key => $v) {
      if ($tree[$key]->subtree) {
        $tree[$key]->subtree = $this->generateIndexAndSort($tree[$key]->subtree);
      }
      $instance = $tree[$key]->link;
      // The weights are made a uniform 5 digits by adding 50000 as an offset.
      // After $this->menuLinkCheckAccess(), $instance->getTitle() has the
      // localized or translated title. Adding the plugin id to the end of the
      // index insures that it is unique.
      $new_tree[(50000 + $instance->getWeight()) . ' ' . $instance->getTitle() . ' ' . $instance->getPluginId()] = $tree[$key];
    }
    ksort($new_tree);
    return $new_tree;
  }

  /**
   * Flattens the tree to a single level.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu link tree to manipulate.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The manipulated menu link tree.
   */
  public function flatten(array $tree) {
    foreach ($tree as $key => $element) {
      if ($tree[$key]->subtree) {
        $tree += $this->flatten($tree[$key]->subtree);
      }
      $tree[$key]->subtree = [];
    }
    return $tree;
  }

}
