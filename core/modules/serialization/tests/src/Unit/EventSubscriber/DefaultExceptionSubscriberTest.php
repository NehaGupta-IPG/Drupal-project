<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\EventSubscriber;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\serialization\Encoder\JsonEncoder;
use Drupal\serialization\EventSubscriber\DefaultExceptionSubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Tests Drupal\serialization\EventSubscriber\DefaultExceptionSubscriber.
 */
#[CoversClass(DefaultExceptionSubscriber::class)]
#[Group('serialization')]
class DefaultExceptionSubscriberTest extends UnitTestCase {

  /**
   * Tests on 4xx.
   */
  public function testOn4xx(): void {
    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $request->setRequestFormat('json');

    $e = new MethodNotAllowedHttpException(['POST', 'PUT'], 'test message');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new DefaultExceptionSubscriber(
      new Serializer([], [new JsonEncoder()]),
      ['xml', 'json'],
      $this->prophesize(ConfigFactoryInterface::class)->reveal()
    );
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('{"message":"test message"}', $response->getContent());
    $this->assertEquals(405, $response->getStatusCode());
    $this->assertEquals('POST, PUT', $response->headers->get('Allow'));
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
  }

  /**
   * @covers ::on5xx
   */
  public function testOn5xx() {
    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $request->setRequestFormat('json');

    $e = new ServiceUnavailableHttpException(60, 'test message');
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new DefaultExceptionSubscriber(
      new Serializer([], [new JsonEncoder()]),
      ['xml', 'json'],
      $this->prophesize(ConfigFactoryInterface::class)->reveal()
    );
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('{"message":"test message"}', $response->getContent());
    $this->assertEquals(503, $response->getStatusCode());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
  }

  /**
   * @covers ::onException
   */
  public function testHideExceptionSpecifics() {
    $kernel = $this->prophesize(HttpKernelInterface::class);
    $request = Request::create('/test');
    $request->setRequestFormat('json');

    $e = new \RuntimeException('this is a runtime exception');
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $config = $this->createMock(Config::class);
    $config->method('get')->with('error_level')->willReturn(ERROR_REPORTING_HIDE);
    $configFactory
      ->method('get')
      ->with('system.logging')
      ->willReturn($config);
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber = new DefaultExceptionSubscriber(
      new Serializer([], [new JsonEncoder()]),
      ['xml', 'json'],
      $configFactory,
    );
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('{"message":"Internal Server Error"}', $response->getContent(), 'The error message is not revealed.');
    $this->assertEquals(500, $response->getStatusCode());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));

    $kernel = $this->prophesize(HttpKernelInterface::class);
    $config = $this->createMock(Config::class);
    $config->method('get')->with('error_level')->willReturn(ERROR_REPORTING_DISPLAY_SOME);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->method('get')
      ->with('system.logging')
      ->willReturn($config);
    $subscriber = new DefaultExceptionSubscriber(
      new Serializer([], [new JsonEncoder()]),
      ['xml', 'json'],
      $configFactory,
    );
    $event = new ExceptionEvent($kernel->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $e);
    $subscriber->onException($event);
    $response = $event->getResponse();

    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals('{"message":"this is a runtime exception"}', $response->getContent(), 'The error message is reported through to the client.');
    $this->assertEquals(500, $response->getStatusCode());
    $this->assertEquals('application/json', $response->headers->get('Content-Type'));
  }

}
