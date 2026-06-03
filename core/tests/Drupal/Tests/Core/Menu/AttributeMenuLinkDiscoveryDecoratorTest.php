<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Menu\AttributeMenuLinkDiscoveryDecorator;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests \Drupal\Core\Menu\AttributeMenuLinkDiscoveryDecorator.
 */
#[CoversClass(AttributeMenuLinkDiscoveryDecorator::class)]
#[Group('Menu')]
class AttributeMenuLinkDiscoveryDecoratorTest extends UnitTestCase {

  /**
   * Builds a decorator scanning a fixtures namespace.
   *
   * @param string $fixture
   *   The fixtures subdirectory ('valid' or 'invalid').
   * @param array $decorated_definitions
   *   Definitions the decorated discovery should return.
   *
   * @return \Drupal\Core\Menu\AttributeMenuLinkDiscoveryDecorator
   *   The decorator under test.
   */
  protected function decorator(string $fixture, array $decorated_definitions = []): AttributeMenuLinkDiscoveryDecorator {
    $decorated = $this->createStub(DiscoveryInterface::class);
    $decorated->method('getDefinitions')->willReturn($decorated_definitions);
    $namespaces = new \ArrayObject([
      "Drupal\\Tests\\Core\\Menu\\fixtures\\$fixture" => $this->root . '/core/tests/Drupal/Tests/Core/Menu/fixtures/' . $fixture,
    ]);
    return new AttributeMenuLinkDiscoveryDecorator($decorated, $namespaces);
  }

  /**
   * Tests that attribute-declared links are merged with decorated definitions.
   */
  public function testGetDefinitions(): void {
    $definitions = $this->decorator('valid', ['yaml.link' => ['id' => 'yaml.link']])->getDefinitions();

    // Definitions from the decorated discovery are preserved.
    $this->assertArrayHasKey('yaml.link', $definitions);

    // The ID and route are derived from the single sibling route.
    $this->assertArrayHasKey('menu_link_test.single', $definitions);
    $single = $definitions['menu_link_test.single'];
    $this->assertSame('menu_link_test.single', $single['route_name']);
    $this->assertSame('system.admin', $single['parent']);
    $this->assertSame(5, $single['weight']);
    $this->assertEquals(new TranslatableMarkup('Single'), $single['title']);
    // The provider is derived from the class namespace.
    $this->assertSame('tests', $single['provider']);
  }

  /**
   * Tests that an explicit ID is honored while the route is still derived.
   */
  public function testExplicitId(): void {
    $definitions = $this->decorator('valid')->getDefinitions();

    $this->assertArrayHasKey('menu_link_test.explicit_id', $definitions);
    $this->assertArrayNotHasKey('menu_link_test.route', $definitions);
    $this->assertSame('menu_link_test.route', $definitions['menu_link_test.explicit_id']['route_name']);
  }

  /**
   * Tests that a link on a multi-route method names the route it points to.
   */
  public function testExplicitRoute(): void {
    $definitions = $this->decorator('valid')->getDefinitions();

    $this->assertArrayHasKey('menu_link_test.b', $definitions);
    $this->assertSame('menu_link_test.b', $definitions['menu_link_test.b']['route_name']);
  }

  /**
   * Tests that a multi-route method without an explicit route fails.
   */
  public function testAmbiguousRouteThrows(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('must specify a "route"');
    $this->decorator('invalid')->getDefinitions();
  }

}
