<?php

namespace Drupal\node\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Local task menu alter for revisions page.
 */
#[Hook('menu_local_tasks_alter')]
class NodeLocalMenuAlter {

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  public function __invoke(&$local_tasks, $route_name, RefinableCacheableDependencyInterface &$cacheability) {
    if ($route_name === 'entity.node.revision') {
      // Show the same local tasks on node revision pages as the ones displayed
      // on node canonical pages.
      $node_canonical_local_tasks = \Drupal::service('plugin.manager.menu.local_task')
        ->getLocalTasks('entity.node.canonical');
      $local_tasks['tabs'][0] = $node_canonical_local_tasks['tabs'];
      // Mark the version history local task as active when viewing a node
      // revision.
      foreach ($local_tasks['tabs'][0] as $route_name => &$tab) {
        $tab['#active'] = $route_name === 'entity.node.version_history';
      }
      $cacheability = CacheableMetadata::createFromObject($cacheability)
        ->merge($node_canonical_local_tasks['cacheability']);
    }
  }

}
