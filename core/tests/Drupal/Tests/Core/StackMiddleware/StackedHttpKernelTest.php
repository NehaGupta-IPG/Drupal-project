<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\StackMiddleware;

use Drupal\Component\Utility\Html;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\StackMiddleware\StackedHttpKernel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Tests Drupal\Core\StackMiddleware\StackedHttpKernel.
 */
#[CoversClass(StackedHttpKernel::class)]
#[Group('StackMiddleware')]
class StackedHttpKernelTest extends UnitTestCase {

  /**
   * Tests HTML state across main requests and their subrequests.
   */
  public function testHtmlRequestState(): void {
    $inner = $this->createStub(HttpKernelInterface::class);
    $kernel = new StackedHttpKernel($inner, new \ArrayIterator([$inner]));
    $subscriber = new AjaxResponseSubscriber(static fn() => NULL);
    $inner->method('handle')->willReturnCallback(static function (Request $request, int $type) use ($kernel, $subscriber): Response {
      $subscriber->onRequest(new RequestEvent($kernel, $request, $type));
      $id = Html::getUniqueId('edit-submit');
      if ($type === HttpKernelInterface::MAIN_REQUEST) {
        $subrequest = $kernel->handle($request->duplicate(), HttpKernelInterface::SUB_REQUEST);
        return new Response($id . ',' . $subrequest->getContent());
      }
      return new Response($id);
    });

    try {
      // Subrequests share the main request's ID counter.
      $this->assertSame('edit-submit,edit-submit--2', $kernel->handle(new Request())->getContent());
      // AJAX requests use distinct random suffixes for both IDs.
      $ajax = $kernel->handle(Request::create('/?_drupal_ajax=1'))->getContent();
      $ids = explode(',', $ajax);
      $this->assertStringStartsWith('edit-submit--', $ids[0]);
      $this->assertStringStartsWith('edit-submit--', $ids[1]);
      $this->assertNotSame($ids[0], $ids[1]);
      // The next main request clears AJAX mode; later requests reset counters.
      $this->assertSame('edit-submit,edit-submit--2', $kernel->handle(new Request())->getContent());
      $this->assertSame('edit-submit,edit-submit--2', $kernel->handle(new Request())->getContent());
    }
    finally {
      Html::resetSeenIds();
      Html::setIsAjax(FALSE);
    }
  }

  /**
   * Tests that stacked kernel is constructed with a list of closures.
   */
  public function testClosureMiddlewareArgument(): void {
    $request = new Request();
    $expected = new Response();
    $basicKernel = $this->createMock(HttpKernelInterface::class);
    $basicKernel->expects($this->once())
      ->method('handle')
      ->with($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->willReturn($expected);

    $stack = new StackedHttpKernel($basicKernel, new \ArrayIterator([$basicKernel]));
    $actual = $stack->handle($request);
    $this->assertSame($expected, $actual);
  }

  /**
   * Tests that stacked kernel invokes the terminate call in all middlewares.
   */
  public function testTerminate(): void {
    $request = new Request();
    $response = new Response();
    $basicKernel = $this->createMockForIntersectionOfInterfaces([HttpKernelInterface::class, TerminableInterface::class]);
    $basicKernel->expects($this->once())
      ->method('terminate')
      ->with($request, $response);

    $outer = $this->createStub(HttpKernelInterface::class);
    $middle = $this->createMockForIntersectionOfInterfaces([HttpKernelInterface::class, TerminableInterface::class]);
    $middle->expects($this->once())
      ->method('terminate')
      ->with($request, $response);

    $inner = $this->createStub(HttpKernelInterface::class);

    $middlewares = new \ArrayIterator([$outer, $middle, $inner, $basicKernel]);
    $stack = new StackedHttpKernel($outer, $middlewares);
    $stack->terminate($request, $response);
  }

}
