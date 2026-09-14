<?php

declare(strict_types=1);

namespace Drupal\Core\Menu;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Default implementation of the menu link tree access check service.
 */
class MenuLinkTreeAccessCheck implements MenuLinkTreeAccessCheckInterface {

  public function __construct(
    protected AccessManagerInterface $accessManager,
    protected AccountInterface $account,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(array $tree): array {
    // Pre-populate access results for menu links pointing at node canonical
    // routes using a single batched query. The standard per-link pass below
    // then sees these as already-set and skips them.
    $node_links = [];
    $this->collectNodeLinks($tree, $node_links);
    if ($node_links) {
      $this->checkNodeLinkAccess($node_links);
    }
    return $this->checkAccessRecursive($tree);
  }

  /**
   * Recursively applies access decisions to each tree element.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree to check.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   The access-checked tree.
   */
  protected function checkAccessRecursive(array $tree): array {
    foreach ($tree as $key => $element) {
      // Other code paths may already have computed access; preserve it.
      if (!isset($element->access)) {
        $tree[$key]->access = $this->menuLinkCheckAccess($element->link);
      }
      if ($tree[$key]->access->isAllowed()) {
        if ($tree[$key]->subtree) {
          $tree[$key]->subtree = $this->checkAccessRecursive($tree[$key]->subtree);
        }
      }
      else {
        // Hide the original link object for inaccessible links so accidental
        // rendering does not leak sensitive information. Keep the top-level
        // inaccessible element so its cacheability metadata can bubble up.
        $tree[$key]->link = new InaccessibleMenuLink($tree[$key]->link);
        $tree[$key]->subtree = [];
      }
    }
    return $tree;
  }

  /**
   * Collects node link elements into a flat structure for batched checking.
   *
   * Pre-sets each collected element's access to neutral; the batched query
   * promotes accessible ones to allowed.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree to inspect.
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[][] $node_links
   *   Output: collected elements grouped by node ID.
   */
  protected function collectNodeLinks(array &$tree, array &$node_links): void {
    foreach ($tree as $key => &$element) {
      if ($element->link->getRouteName() === 'entity.node.canonical') {
        $nid = $element->link->getRouteParameters()['node'];
        $node_links[$nid][$key] = $element;
        $element->access = AccessResult::neutral();
      }
      if ($element->hasChildren) {
        $this->collectNodeLinks($element->subtree, $node_links);
      }
    }
  }

  /**
   * Runs the batched access query for collected node link elements.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[][] $node_links
   *   Elements grouped by node ID, as populated by collectNodeLinks().
   */
  protected function checkNodeLinkAccess(array $node_links): void {
    $nids = array_keys($node_links);
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->accessCheck(TRUE);
    $query->condition('nid', $nids, 'IN');

    // Disable node_access query rewriting for users that can bypass it; for
    // other users, vary the result by node_grants and only restrict to
    // published nodes when no contrib node_grants module is installed and the
    // user lacks the 'view any unpublished content' permission. The 'view own
    // unpublished nodes' permission is intentionally ignored to avoid per-user
    // cache entries.
    $access_result = AccessResult::allowed()->cachePerPermissions();
    if ($this->account->hasPermission('bypass node access')) {
      $query->accessCheck(FALSE);
    }
    else {
      $access_result->addCacheContexts(['user.node_grants:view']);
      if (!$this->moduleHandler->hasImplementations('node_grants') && !$this->account->hasPermission('view any unpublished content')) {
        $query->condition('status', NodeInterface::PUBLISHED);
      }
    }

    $accessible_nids = $query->execute();
    foreach ($accessible_nids as $nid) {
      foreach ($node_links[$nid] as $key => $element) {
        $node_links[$nid][$key]->access = $access_result;
      }
    }
  }

  /**
   * Performs the access check for a single menu link.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $instance
   *   The menu link instance.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function menuLinkCheckAccess(MenuLinkInterface $instance) {
    $url = $instance->getUrlObject();
    if ($url->isRouted()) {
      return $this->accessManager->checkNamedRoute($url->getRouteName(), $url->getRouteParameters(), $this->account, TRUE);
    }
    return AccessResult::allowed();
  }

}
