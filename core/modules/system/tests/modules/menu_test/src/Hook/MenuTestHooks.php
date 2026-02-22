<?php

declare(strict_types=1);

namespace Drupal\menu_test\Hook;

use Drupal\Core\Url;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\menu_test\MenuTestHelper;

/**
 * Hook implementations for menu_test.
 */
class MenuTestHooks {

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links): void {
    // Many of the machine names here are slightly different from the route
    // name. Since the machine name is arbitrary, this helps ensure that core
    // does not add mistaken assumptions about the correlation.
    $links['menu_test.menu_name_test']['menu_name'] = MenuTestHelper::menuName();
    $links['menu_test.context']['title'] = \Drupal::config('menu_test.menu_item')->get('title');
    // Adds a custom menu link.
    $links['menu_test.custom'] = [
      'title' => 'Custom link',
      'route_name' => 'menu_test.custom',
      'description' => 'Custom link used to check the integrity of manually added menu links.',
      'parent' => 'menu_test',
    ];
  }

  /**
   * Implements hook_local_tasks_render_alter().
   */
  #[Hook('local_tasks_render_alter')]
  public function localTasksRenderAlter(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
    if (in_array($route_name, ['menu_test.tasks_default'])) {
      $data['tabs'][0]['foo'] = [
        '#theme' => 'menu_local_task',
        '#link' => [
          'title' => "Task 1 <script>alert('Welcome to the jungle!')</script>",
          'url' => Url::fromRoute('menu_test.router_test1', [
            'bar' => '1',
          ]),
        ],
        '#weight' => 10,
      ];
      $data['tabs'][0]['bar'] = [
        '#theme' => 'menu_local_task',
        '#link' => [
          'title' => 'Task 2',
          'url' => Url::fromRoute('menu_test.router_test2', [
            'bar' => '2',
          ]),
        ],
        '#weight' => 20,
      ];
    }
    $cacheability->addCacheTags(['kittens:dwarf-cat']);
  }

  /**
   * Implements hook_local_actions_render_alter().
   */
  #[Hook('local_actions_render_alter')]
  public function localActionsRenderAlter(&$data, $route_appears): void {
    // Change the link title for local_action2 if on test route 6.
    if ($route_appears === 'menu_test.local_action6') {
      $data['menu_test.local_action2']['#link']['title'] = 'Dynamic title override';
    }
  }

}
