<?php

namespace Drupal\Tests\menu_link_content\Unit;

use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent
 *
 * @group Menu
 */
class MenuLinkPluginTest extends UnitTestCase {

  /**
   * @covers ::getUuid
   */
  public function testGetInstanceReflection() {
    /** @var \Prophecy\Prophecy\ObjectProphecy $menu_link_content_plugin */
    $menu_link_content_plugin = $this->prophesize(MenuLinkContent::class);
    /** @var \Prophecy\Prophecy\MethodProphecy $get_derivative_id */
    $get_derivative_id = $menu_link_content_plugin->getDerivativeId();
    $get_derivative_id->willReturn('test_id');
    $menu_link_content_plugin = $menu_link_content_plugin->reveal();

    $class = new \ReflectionClass(MenuLinkContent::class);
    $instance_method = $class->getMethod('getUuid');
    $instance_method->setAccessible(TRUE);

    $this->assertEquals('test_id', $instance_method->invoke($menu_link_content_plugin));
  }

}
