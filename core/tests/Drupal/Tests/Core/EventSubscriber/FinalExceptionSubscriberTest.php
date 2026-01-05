<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\EventSubscriber;

use Drupal\Core\EventSubscriber\FinalExceptionSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests Drupal\Core\EventSubscriber\FinalExceptionSubscriber.
 */
#[CoversClass(FinalExceptionSubscriber::class)]
#[Group('EventSubscriber')]
class FinalExceptionSubscriberTest extends UnitTestCase {

  /**
   * Tests on exception with unknown format.
   *
   * @legacy-covers ::onException
   */
  public function testOnExceptionWithUnknownFormat(): void {
    $config_factory = $this->getConfigFactoryStub();

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    // \Drupal\Core\StackMiddleware\NegotiationMiddleware normally takes care
    // of this so we'll hard code it here.
    $request->setRequestFormat('bananas');
    $e = new MethodNotAllowedHttpException(['POST', 'PUT'], 'test message');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new TestDefaultExceptionSubscriber($config_factory);
    $subscriber->setStringTranslation($this->getStringTranslationStub());
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);

    $this->assertStringStartsWith(strtr(
      'The website encountered an unexpected error. Try again later.<br><br><em class="placeholder">:exception</em>: test message in <em class="placeholder">:method</em> (line <em class="placeholder">:line</em> of <em class="placeholder">:file</em>). <pre class="backtrace">:trace',
      [
        ':exception' => MethodNotAllowedHttpException::class,
        ':method' => self::class . '-&gt;' . __FUNCTION__ . '()',
        ':line' => $e->getLine(),
        ':file' => $e->getFile(),
        // Assert only the first two backtrace items.
        // The method reported for the first backtrace item is the same as
        // already reported. However the line number and file path in the first
        // backtrace item are different. They describe where the method is
        // called, not where the exception is thrown.
        ':trace' => self::class . '-&gt;' . __FUNCTION__ . '() (Line: ' . $e->getTrace()[0]['line'] . ')
' . $e->getTrace()[1]['class'] . '-&gt;' . $e->getTrace()[1]['function'] . '() (Line: ' . $e->getTrace()[1]['line'],
      ],
    ), $response->getContent());

    $this->assertEquals(405, $response->getStatusCode());
    $this->assertEquals('POST, PUT', $response->headers->get('Allow'));
    // Also check that the text/plain content type was added.
    $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
  }

}

/**
 * Test class for testing the final exception subscriber.
 */
class TestDefaultExceptionSubscriber extends FinalExceptionSubscriber {

  protected function isErrorDisplayable($error): bool {
    return TRUE;
  }

  protected function simplifyFileInError($error) {
    return $error;
  }

  protected function isErrorLevelVerbose(): bool {
    return TRUE;
  }

}
