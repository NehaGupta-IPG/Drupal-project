<?php

declare(strict_types=1);

namespace Drupal\Tests\big_pipe\Unit\Render;

use Drupal\big_pipe\Render\BigPipe;
use Drupal\big_pipe\Render\BigPipeResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests Drupal\big_pipe\Render\BigPipe.
 */
#[CoversClass(BigPipe::class)]
#[Group('big_pipe')]
class ManyPlaceholderTest extends UnitTestCase {

  /**
   * Tests many no js place holders.
   *
   * @legacy-covers \Drupal\big_pipe\Render\BigPipe::sendNoJsPlaceholders
   */
  public function testManyNoJsPlaceHolders(): void {
    $session = $this->prophesize(SessionInterface::class);
    $session->start()->willReturn(TRUE);
    $session->save()->shouldBeCalled();
    $bigpipe = new BigPipe(
      $this->prophesize(RendererInterface::class)->reveal(),
      $session->reveal(),
      $this->prophesize(RequestStack::class)->reveal(),
      $this->prophesize(HttpKernelInterface::class)->reveal(),
      $this->prophesize(EventDispatcherInterface::class)->reveal(),
      $this->prophesize(ConfigFactoryInterface::class)->reveal(),
      $this->prophesize(MessengerInterface::class)->reveal(),
      $this->prophesize(RequestContext::class)->reveal(),
      $this->prophesize(LoggerInterface::class)->reveal(),
    );
    $response = new BigPipeResponse(new HtmlResponse());

    // Add many placeholders.
    $many_placeholders = [];
    for ($i = 0; $i < 400; $i++) {
      $many_placeholders[$this->randomMachineName(80)] = $this->randomMachineName(80);
    }
    $attachments = [
      'library' => [],
      'big_pipe_nojs_placeholders' => $many_placeholders,
    ];
    $response->setAttachments($attachments);

    // Construct minimal HTML response.
    $content = '<html><body>content<drupal-big-pipe-scripts-bottom-marker>script-bottom<drupal-big-pipe-scripts-bottom-marker></body></html>';
    $response->setContent($content);

    // Capture the result to avoid PHPUnit complaining.
    ob_start();
    $bigpipe->sendContent($response);
    $result = ob_get_clean();

    $this->assertNotEmpty($result);
  }

  /**
   * Tests that duplicate libraries from no-JS placeholders are deduplicated.
   *
   * @legacy-covers \Drupal\big_pipe\Render\BigPipe::sendNoJsPlaceholders
   */
  public function testNoJsPlaceholdersDeduplicateLibraries(): void {
    $session = $this->prophesize(SessionInterface::class);
    $session->save()->shouldBeCalled();

    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->renderPlaceholder(Argument::any(), Argument::any())
      ->willReturn(['#markup' => '', '#attached' => []]);

    // Each placeholder render returns core/jquery. Track the ajax_page_state
    // libraries value seen on entry to each subsequent render to verify the
    // cumulative list stays deduplicated.
    $captured_libraries = [];
    $event_dispatcher = $this->prophesize(EventDispatcherInterface::class);
    $event_dispatcher->dispatch(Argument::cetera())
      ->will(function ($args) use (&$captured_libraries) {
        $event = $args[0];
        $page_state = $event->getRequest()->attributes->get('ajax_page_state');
        if (isset($page_state['libraries'])) {
          $captured_libraries[] = $page_state['libraries'];
        }
        $response = new HtmlResponse();
        $response->setAttachments(['library' => ['core/jquery'], 'drupalSettings' => []]);
        $event->setResponse($response);
        return $event;
      });

    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack->getMainRequest()->willReturn(new Request());
    $request_stack->push(Argument::type(Request::class))->willReturn(null);
    $request_stack->pop()->willReturn(null);

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('error_level')->willReturn('hide');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('system.logging')->willReturn($config->reveal());

    $bigpipe = new BigPipe(
      $renderer->reveal(),
      $session->reveal(),
      $request_stack->reveal(),
      $this->prophesize(HttpKernelInterface::class)->reveal(),
      $event_dispatcher->reveal(),
      $config_factory->reveal(),
      $this->prophesize(MessengerInterface::class)->reveal(),
      $this->prophesize(RequestContext::class)->reveal(),
      $this->prophesize(LoggerInterface::class)->reveal(),
    );

    $response = new BigPipeResponse(new HtmlResponse());
    $response->setAttachments([
      'library' => [],
      'big_pipe_nojs_placeholders' => [
        'placeholder_1' => ['#markup' => ''],
        'placeholder_2' => ['#markup' => ''],
        'placeholder_3' => ['#markup' => ''],
      ],
    ]);
    $response->setContent('<html><body>placeholder_1placeholder_2placeholder_3<drupal-big-pipe-scripts-bottom-marker>scripts<drupal-big-pipe-scripts-bottom-marker></body></html>');

    ob_start();
    $bigpipe->sendContent($response);
    ob_end_clean();

    // The third render sees the libraries accumulated by the first two. Without
    // array_unique() the value would be 'core/jquery,core/jquery'.
    $this->assertSame('core/jquery', end($captured_libraries));
  }

  /**
   * Tests that a \Throwable from a no-JS placeholder is caught, not rethrown.
   *
   * @legacy-covers \Drupal\big_pipe\Render\BigPipe::sendNoJsPlaceholders
   */
  public function testThrowableFromNoJsPlaceholderIsCaughtAndLogged(): void {
    $session = $this->prophesize(SessionInterface::class);
    $session->save()->shouldBeCalled();

    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->renderPlaceholder(Argument::any(), Argument::any())
      ->willThrow(new \AssertionError('$libraries cannot contain duplicate items'));

    $config = $this->prophesize(ImmutableConfig::class);
    $config->get('error_level')->willReturn('hide');
    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('system.logging')->willReturn($config->reveal());

    $bigpipe = new BigPipe(
      $renderer->reveal(),
      $session->reveal(),
      $this->prophesize(RequestStack::class)->reveal(),
      $this->prophesize(HttpKernelInterface::class)->reveal(),
      $this->prophesize(EventDispatcherInterface::class)->reveal(),
      $config_factory->reveal(),
      $this->prophesize(MessengerInterface::class)->reveal(),
      $this->prophesize(RequestContext::class)->reveal(),
      $this->prophesize(LoggerInterface::class)->reveal(),
    );

    $response = new BigPipeResponse(new HtmlResponse());
    $response->setAttachments([
      'library' => [],
      'big_pipe_nojs_placeholders' => [
        'placeholder_1' => ['#markup' => ''],
      ],
    ]);
    $response->setContent('<html><body>placeholder_1<drupal-big-pipe-scripts-bottom-marker>scripts<drupal-big-pipe-scripts-bottom-marker></body></html>');

    $triggered_errors = [];
    set_error_handler(static function (int $errno, string $errstr) use (&$triggered_errors): bool {
      $triggered_errors[] = $errstr;
      return TRUE;
    }, E_USER_WARNING);

    try {
      ob_start();
      $bigpipe->sendContent($response);
      ob_end_clean();
    }
    finally {
      restore_error_handler();
    }

    $this->assertNotEmpty($triggered_errors, 'A \Throwable thrown during no-JS placeholder rendering must be caught and passed to trigger_error().');
  }

}
