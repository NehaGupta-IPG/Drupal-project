<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the routes and builds that serve rows through core's htmx pipeline.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeHtmxRoutesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);
  }

  /**
   * The rows route is an htmx route that is neither cached nor streamed.
   */
  public function testChildrenRouteOptions(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('menu_tree_ui.children');

    $this->assertTrue($route->getOption('_htmx_route'));
    $this->assertTrue($route->getOption('no_cache'));
    $this->assertTrue($route->getOption('_no_big_pipe'));
    $this->assertNull($route->getDefault('_title'));
  }

  /**
   * A child level's build has one row per child and the paging headers.
   */
  public function testChildLevelBuild(): void {
    $parent = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'Parent',
      'link' => ['uri' => 'route:<nolink>'],
    ]);
    $parent->save();
    foreach (['One', 'Two'] as $title) {
      MenuLinkContent::create([
        'menu_name' => 'main',
        'title' => $title,
        'link' => ['uri' => 'route:<nolink>'],
        'parent' => $parent->getPluginId(),
      ])->save();
    }

    $menu = $this->container->get('entity_type.manager')->getStorage('menu')->load('main');
    $request = Request::create('/children', 'GET', ['parent' => $parent->getPluginId()]);
    $build = MenuTreeController::create($this->container)->children($menu, $request);

    $this->assertCount(2, $build['#rows']);
    $this->assertSame(0, $build['#cache']['max-age']);
    $names = array_column($build['#attached']['http_header'], 0);
    $this->assertContains('X-Treegrid-Rows', $names);
    $this->assertNotContains('X-Treegrid-Next-Id', $names);
  }

}
