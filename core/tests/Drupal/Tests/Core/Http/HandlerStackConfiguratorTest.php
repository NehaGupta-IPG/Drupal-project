<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Http;

use Drupal\Core\Http\HandlerStackConfigurator;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Http\HandlerStackConfigurator.
 */
#[CoversClass(HandlerStackConfigurator::class)]
#[Group('Http')]
class HandlerStackConfiguratorTest extends UnitTestCase {

  /**
   * Tests that an iterable of middleware services configures the handler stack.
   *
   * @legacy-covers ::configure
   */
  public function testConfigureWithIterable(): void {
    $middleware_called = FALSE;
    $middleware = static function () use (&$middleware_called): callable {
      return static function (callable $handler) use (&$middleware_called): callable {
        return static function ($request, array $options) use ($handler, &$middleware_called) {
          $middleware_called = TRUE;
          return $handler($request, $options);
        };
      };
    };
    $configurator = new HandlerStackConfigurator(new \ArrayIterator(['test_middleware' => $middleware]));
    $handler_stack = HandlerStack::create(static fn () => Create::promiseFor(new Response()));

    $configurator->configure($handler_stack);
    $handler_stack->resolve()(new Request('GET', 'https://example.com'), []);

    $this->assertTrue($middleware_called);
  }

}
