<?php

declare(strict_types=1);

namespace Drupal\Tests\big_pipe\Unit\Render;

use Drupal\big_pipe\Render\BigPipe;
use Drupal\big_pipe\Render\BigPipeResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Assert;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @coversDefaultClass \Drupal\big_pipe\Render\BigPipe
 * @group big_pipe
 */
class ChunkCacheTest extends UnitTestCase {

  /**
   * Tests that sent chunks are cacheable.
   *
   * @covers \Drupal\big_pipe\Render\BigPipe::sendChunk
   */
  public function testSendChunkCache(): void {
    $placeholderId = 'test-placeholder-id';
    $noJsPlaceholderId = 'test-nojs-placeholder-id';

    $session = $this->prophesize(SessionInterface::class);
    $session->start()->willReturn(TRUE);
    $session->save()->shouldBeCalled();
    $requestStack = $this->prophesize(RequestStack::class);
    $requestStack->getMainRequest()->willReturn(new Request());
    $requestStack->push(Argument::type(Request::class));
    $requestStack->pop();
    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->renderPlaceholder($placeholderId, Argument::type('array'))
      ->willReturn(['#markup' => 'test-markup', '#attached' => []]);
    $renderer->renderPlaceholder($noJsPlaceholderId, Argument::type('array'))
      ->willReturn(['#markup' => 'test-markup']);
    $messenger = $this->prophesize(MessengerInterface::class);
    $messenger->deleteAll()->willReturn([]);
    $bigpipe = new TestBigPipe(
      $renderer->reveal(),
      $session->reveal(),
      $requestStack->reveal(),
      $this->prophesize(HttpKernelInterface::class)->reveal(),
      new TestEventDispatcher(),
      $this->prophesize(ConfigFactoryInterface::class)->reveal(),
      $messenger->reveal(),
      $this->prophesize(RequestContext::class)->reveal(),
      $this->prophesize(LoggerInterface::class)->reveal(),
    );

    $response = new BigPipeResponse(new HtmlResponse());

    $attachments = [
      'library' => [],
      'big_pipe_placeholders' => [$placeholderId => ['#markup' => 'test-markup']],
      'big_pipe_nojs_placeholders' => [$noJsPlaceholderId => 'test-markup'],
    ];
    $response->setAttachments($attachments);

    $content = '<html><body>content<span data-big-pipe-placeholder-id="' . $placeholderId . '">
    <span data-big-pipe-nojs-placeholder-id="' . $noJsPlaceholderId . '">
    <drupal-big-pipe-scripts-bottom-marker>script-bottom<drupal-big-pipe-scripts-bottom-marker></body></html>';
    $response->setContent($content);

    // Capture the result to avoid PHPUnit complaining.
    ob_start();
    $bigpipe->sendContent($response);
    $result = ob_get_clean();

    $this->assertNotEmpty($result);
  }

}

/**
 * Event dispatcher used to add attachments needed by the test to the response.
 */
class TestEventDispatcher implements EventDispatcherInterface {

  /**
   * {@inheritdoc}
   */
  public function dispatch(object $event, ?string $eventName = NULL): object {
    assert($event instanceof ResponseEvent);
    $response = $event->getResponse();
    if ($response instanceof HtmlResponse) {
      $response->addAttachments([
        'library' => [],
        'drupalSettings' => ['ajaxPageState' => []],
      ]);
    }

    return $event;
  }

}

/**
 * Extends BigPipe to assert chunks are cacheable.
 */
class TestBigPipe extends BigPipe {

  /**
   * {@inheritdoc}
   */
  protected function sendChunk($chunk): void {
    parent::sendChunk($chunk);
    if ($chunk instanceof HtmlResponse) {
      // Checks that HTML chunks are cacheable.
      Assert::assertEquals(-1, $chunk->getCacheableMetadata()
        ->getCacheMaxAge());
    }
    else {
      // Checks that we don't send embedded AJAX responses as string.
      Assert::assertStringStartsNotWith(
        '<script type="application/vnd.drupal-ajax" data-big-pipe-replacement-for-placeholder-with-id',
        $chunk
      );
    }
  }

}
