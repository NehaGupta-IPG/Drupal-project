<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Hook;

use Drupal\Core\Hook\HookCollectorKeyValueWritePass;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\Core\Hook\HookCollectorKeyValueWritePass.
 */
#[CoversClass(HookCollectorKeyValueWritePass::class)]
#[Group('Hook')]
#[RunTestsInSeparateProcesses]
class HookCollectorKeyValueWritePassTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'hook_collector_keyvalue_write_test',
  ];

  /**
   * Tests the services that the pass creates while the container compiles.
   */
  public function testCreatedServices(): void {
    $this->doTestEventDispatcherListeners();
    $this->doTestRouterRebuild();
  }

  /**
   * Tests that the event dispatcher has listeners.
   */
  private function doTestEventDispatcherListeners(): void {
    $this->assertTrue($this->container->get('event_dispatcher')->hasListeners(RoutingEvents::STATIC));
  }

  /**
   * Tests that a router rebuild adds the static routes.
   */
  private function doTestRouterRebuild(): void {
    $this->container->get('router.builder')->rebuild();
    $this->assertCount(1, $this->container->get('router.route_provider')->getRoutesByNames(['system.admin']));
  }

}
