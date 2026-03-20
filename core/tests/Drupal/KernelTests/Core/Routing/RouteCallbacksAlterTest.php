<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Routing;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the route_callbacks_alter.
 *
 * @group Routing
 */
class RouteCallbacksAlterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system'];

  /**
   * Tests creating a PathChangedHelper object and getting paths.
   */
  public function testRouteCallbacksAlter(): void {

    // 1. Enable first route_callbacks.
    $this->enableModules(['router_route_callback_primary_test']);
    drupal_flush_all_caches();
    /** @var \Symfony\Component\Routing\Route $route */
    $route_primary = \Drupal::service('router.route_provider')->getRouteByName('route_callback.primary.test');
    $this->assertEquals('/route_callback/primary/test', $route_primary->getPath());

    // 2. Enable second route_callbacks - should replace first.
    $this->enableModules(['router_route_callback_secondary_test']);
    drupal_flush_all_caches();
    /** @var \Symfony\Component\Routing\Route $route */
    $route_primary = \Drupal::service('router.route_provider')->preLoadRoutes(['route_callback.primary.test']);
    $this->assertNull($route_primary);

    $route_secondary = \Drupal::service('router.route_provider')->getRouteByName('route_callback.secondary.test');
    $this->assertEquals('/route_callback/secondary/test', $route_secondary->getPath());

    // 3. Enable third route_callbacks
    // - should replace second according to weight.
    $this->enableModules(['router_route_callback_third_test']);
    drupal_flush_all_caches();
    /** @var \Symfony\Component\Routing\Route $route */
    $route_primary = \Drupal::service('router.route_provider')->preLoadRoutes(['route_callback.primary.test']);
    $this->assertNull($route_primary);

    $route_secondary = \Drupal::service('router.route_provider')->preLoadRoutes(['route_callback.secondary.test']);
    $this->assertNull($route_secondary);

    $route_third = \Drupal::service('router.route_provider')->getRouteByName('route_callback.third.test');
    $this->assertEquals('/route_callback/third/test', $route_third->getPath());

  }

}
