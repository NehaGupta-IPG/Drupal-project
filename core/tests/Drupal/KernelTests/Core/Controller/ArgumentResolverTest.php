<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Controller;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the argument resolvers.
 */
#[Group('Controller')]
#[RunTestsInSeparateProcesses]
class ArgumentResolverTest extends KernelTestBase {

  /**
   * Tests that controller arguments are resolved correctly.
   */
  public function testArgumentResolvers(): void {
    $argumentResolver = $this->container->get('http_kernel.controller.argument_resolver');
    $request = Request::create('/test');
    $request->attributes->set('id', 42);
    $arguments = $argumentResolver->getArguments($request, [$this, 'controllerMethod']);

    $this->assertCount(8, $arguments);
    $this->assertSame(42, $arguments[0]);
    $this->assertSame($request, $arguments[1]);
    $this->assertInstanceOf(ServerRequestInterface::class, $arguments[2]);
    $this->assertInstanceOf(RouteMatchInterface::class, $arguments[3]);
    $this->assertSame($this->container->get('lock'), $arguments[4]);
    $this->assertSame($this->container->get(RendererInterface::class), $arguments[5]);
    $this->assertSame('default', $arguments[6]);
    $this->assertNull($arguments[7]);
  }

  /**
   * Controller method with various argument types.
   */
  public function controllerMethod(
    int $id,
    Request $request,
    ServerRequestInterface $psr7Request,
    RouteMatchInterface $routeMatch,
    #[Autowire(service: 'lock')]
    LockBackendInterface $lock,
    RendererInterface $renderer,
    string $optional = 'default',
    ?string $nullable = NULL,
  ): void {}

}
