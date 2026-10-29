<?php

declare(strict_types=1);

namespace Drupal\node\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Menu hook implementations for node.
 */
class NodeMenuHooks {

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks): void {
    // Removes 'Revisions' local task added by deriver. Local task
    // 'entity.node.version_history' will be replaced by
    // 'entity.version_history:node.version_history' after
    // https://www.drupal.org/project/drupal/issues/3153559.
    unset($local_tasks['entity.version_history:node.version_history']);
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function nodeLocalMenuAlter(&$local_tasks, $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
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
