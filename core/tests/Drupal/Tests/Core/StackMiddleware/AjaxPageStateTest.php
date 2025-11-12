<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\StackMiddleware;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StackMiddleware\AjaxPageState;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @coversDefaultClass \Drupal\Core\StackMiddleware\AjaxPageState
 * @group Middleware
 */
class AjaxPageStateTest extends UnitTestCase {

  /**
   * Tests that sub-requests are not processed.
   *
   * @covers ::handle
   */
  public function testHandle(): void {
    $compressed = UrlHelper::compressQueryParameter('core/drupal');
    $request = Request::create('/');
    $request->request->set('ajax_page_state', ['libraries' => $compressed]);

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $kernel->handle($request, HttpKernelInterface::SUB_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($kernel->reveal());
    $middleware->handle($request, HttpKernelInterface::SUB_REQUEST);
    $ajax_page_state = $request->request->all('ajax_page_state');
    $this->assertSame($compressed, $ajax_page_state['libraries']);
  }

  /**
   * Tests that libraries key is set to empty string when missing.
   *
   * @covers ::handle
   */
  public function testLibrariesKeyMissing(): void {
    $request = Request::create('/');
    $request->request->set('ajax_page_state', ['theme' => 'stark']);

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($kernel->reveal());
    $middleware->handle($request);
    $ajax_page_state = $request->request->all('ajax_page_state');
    $this->assertSame('', $ajax_page_state['libraries']);
  }

  /**
   * Tests that non-string libraries value is set to empty string.
   *
   * @covers ::handle
   */
  public function testLibrariesNonString(): void {
    $request = Request::create('/');
    $request->request->set('ajax_page_state', ['libraries' => ['core/drupal']]);

    $app = $this->prophesize(HttpKernelInterface::class);
    $app->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($app->reveal());
    $middleware->handle($request);
    $ajax_page_state = $request->request->all('ajax_page_state');
    $this->assertSame('', $ajax_page_state['libraries']);
  }

  /**
   * Tests that compressed libraries are decompressed.
   *
   * @covers ::handle
   */
  public function testLibrariesDecompressed(): void {
    $libraries = 'core/drupal,core/jquery';
    $compressed = UrlHelper::compressQueryParameter($libraries);
    $request = Request::create('/');
    $request->request->set('ajax_page_state', ['libraries' => $compressed]);

    $app = $this->prophesize(HttpKernelInterface::class);
    $app->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($app->reveal());
    $middleware->handle($request);
    $ajax_page_state = $request->request->all('ajax_page_state');
    $this->assertSame($libraries, $ajax_page_state['libraries']);
  }

  /**
   * Tests that query parameters are processed.
   *
   * @covers ::handle
   */
  public function testQueryParameters(): void {
    $libraries = 'core/drupal';
    $compressed = UrlHelper::compressQueryParameter($libraries);
    $request = Request::create('/');
    $request->query->set('ajax_page_state', ['libraries' => $compressed]);

    $app = $this->prophesize(HttpKernelInterface::class);
    $app->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($app->reveal());
    $middleware->handle($request);
    $ajax_page_state = $request->query->all('ajax_page_state');
    $this->assertSame($libraries, $ajax_page_state['libraries']);
  }

  /**
   * Tests that handle works without ajax_page_state.
   *
   * @covers ::handle
   */
  public function testHandleWithoutAjaxPageState(): void {
    $request = Request::create('/');

    $app = $this->prophesize(HttpKernelInterface::class);
    $app->handle($request, HttpKernelInterface::MAIN_REQUEST, TRUE)
      ->shouldBeCalled()
      ->willReturn(new Response());

    $middleware = new AjaxPageState($app->reveal());
    $middleware->handle($request);
  }

}
