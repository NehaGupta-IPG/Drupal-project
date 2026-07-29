<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Unit\Plugin\views\argument_default;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Plugin\views\argument_default\Node;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the node argument default plugin.
 */
#[CoversClass(Node::class)]
#[Group('node')]
class NodeTest extends UnitTestCase {

  /**
   * Tests the cache contexts.
   */
  public function testCacheContexts(): void {
    $route_match = $this->createStub(RouteMatchInterface::class);
    $plugin = new Node([], 'node', [], $route_match);

    $this->assertSame(['route'], $plugin->getCacheContexts());
  }

}
