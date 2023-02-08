<?php

declare(strict_types=1);

namespace Drupal\node_access_test_auto_bubbling\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

/**
 * Returns a node ID listing.
 */
class NodeAccessTestAutoBubblingController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Lists the three latest published node IDs.
   *
   * @param string|int|null $uid
   *   (optional) Check node access by the user with the given id, default is
   *   the current user.
   *
   * @return array
   *   A render array.
   */
  public function latest(string|int|null $uid = NULL) {
    $query = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->range(0, 3);
    if (!empty($uid)) {
      $query->addMetaData('account', User::load($uid));
    }
    $nids = $query->execute();
    return ['#markup' => $this->t('The three latest nodes are: @nids.', ['@nids' => implode(', ', $nids)])];
  }

  /**
   * Exposes the node label when a user has access to view a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node entity.
   *
   * @return array
   *   A render array.
   */
  public function nodeAccessCacheability(NodeInterface $node) {
    $build = [];
    $cacheability = new CacheableMetadata();
    $access = $node->access('view', return_as_object: TRUE);
    $cacheability->addCacheableDependency($access);
    if ($access->isAllowed()) {
      $build[] = [
        '#markup' => $node->label(),
      ];
      $cacheability->addCacheableDependency($node);
    }
    $cacheability->applyTo($build);
    return $build;
  }

}
