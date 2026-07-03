<?php

namespace Drupal\Tests\Core\Routing;

use Drupal\Core\Routing\RequestContext;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\Core\Routing\RequestContext
 * @group Routing
 */
class RequestContextTest extends UnitTestCase {

  /**
   * Tests that an empty request stack does not throw a TypeError.
   *
   * This can happen during request-less CLI execution, such as a route
   * rebuild triggered from a module's install hook or event subscriber
   * while running `drush site:install`, where the request stack has no
   * current request yet.
   *
   * @covers ::fromRequestStack
   */
  public function testFromRequestStackWithEmptyStack(): void {
    $request_context = new RequestContext();
    $request_stack = new RequestStack();

    // This must not throw
    // \TypeError: Drupal\Core\Routing\RequestContext::fromRequest():
    // Argument #1 ($request) must be of type Symfony\Component\
    // HttpFoundation\Request, null given.
    $request_context->fromRequestStack($request_stack);

    // The context is left at its constructor defaults.
    $this->assertSame('localhost', $request_context->getHost());
  }

  /**
   * Tests that a populated request stack still populates the context.
   *
   * @covers ::fromRequestStack
   */
  public function testFromRequestStackWithCurrentRequest(): void {
    $request_context = new RequestContext();
    $request = Request::create('https://example.com/foo');
    $request_stack = new RequestStack();
    $request_stack->push($request);

    $request_context->fromRequestStack($request_stack);

    $this->assertSame('example.com', $request_context->getHost());
  }

}
