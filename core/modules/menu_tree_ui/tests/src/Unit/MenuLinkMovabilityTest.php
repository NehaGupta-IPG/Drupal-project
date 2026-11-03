<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Unit;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\menu_tree_ui\MenuLinkMovability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MenuLinkMovability::isMovable().
 */
#[CoversClass(MenuLinkMovability::class)]
#[Group('menu_tree_ui')]
final class MenuLinkMovabilityTest extends UnitTestCase {

  /**
   * A content link (menu_link_content:{uuid}) is movable.
   */
  public function testContentLinkIsMovable(): void {
    $link = $this->createStub(MenuLinkInterface::class);
    $link->method('getPluginId')->willReturn('menu_link_content:1f5b3f1a-0000-4000-8000-000000000000');

    $this->assertTrue(MenuLinkMovability::isMovable($link));
  }

  /**
   * A MenuLinkDefault instance is movable regardless of its plugin ID.
   *
   * The instanceof branch wins independently of the prefix check: a mocked
   * MenuLinkDefault with a static (non menu_link_content:) plugin ID is still
   * reported movable, because *.links.menu.yml links persist parent/weight
   * via StaticMenuLinkOverrides.
   */
  public function testMenuLinkDefaultIsMovable(): void {
    $link = $this->createStub(MenuLinkDefault::class);
    $link->method('getPluginId')->willReturn('system.admin_content');

    $this->assertTrue(MenuLinkMovability::isMovable($link));
  }

  /**
   * A static link that is neither content-backed nor a MenuLinkDefault stays locked.
   */
  public function testBespokeStaticLinkIsNotMovable(): void {
    $link = $this->createStub(MenuLinkInterface::class);
    $link->method('getPluginId')->willReturn('system.admin');

    $this->assertFalse(MenuLinkMovability::isMovable($link));
  }

}
