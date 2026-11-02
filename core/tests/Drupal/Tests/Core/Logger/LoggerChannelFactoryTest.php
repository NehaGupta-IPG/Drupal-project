<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Logger;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests Drupal\Core\Logger\LoggerChannelFactory.
 */
#[CoversClass(LoggerChannelFactory::class)]
#[Group('Logger')]
class LoggerChannelFactoryTest extends UnitTestCase {

  /**
   * Tests that injected loggers are only initialized when a channel is requested.
   */
  public function testLazyLoggers(): void {
    $iterations = 0;
    $logger = new TestLogger();
    $loggers = (static function () use (&$iterations, $logger): \Generator {
      $iterations++;
      yield $logger;
    })();
    $factory = new LoggerChannelFactory(
      new RequestStack(),
      $this->createStub(AccountInterface::class),
      $loggers,
    );
    $this->assertSame(0, $iterations);

    $channel = $factory->get('test');
    $this->assertSame(1, $iterations);
    $channel->notice('First message');
    $factory->get('another')->notice('Second message');
    $this->assertSame($channel, $factory->get('test'));
    $this->assertCount(2, $logger->records);
    $this->assertSame(1, $iterations);
  }

  /**
   * Tests LoggerChannelFactory::get().
   */
  public function testGet(): void {
    $factory = new LoggerChannelFactory(
      $this->createStub(RequestStack::class),
      $this->createStub(AccountInterface::class),
    );

    // Ensure that when called with the same argument, always the same instance
    // will be returned.
    $this->assertSame($factory->get('test'), $factory->get('test'));
  }

  /**
   * Tests requesting a channel while a logger service is being constructed.
   */
  #[TestWith(['test'])]
  #[TestWith(['another'])]
  public function testLoggerChannelDependency(string $channelName): void {
    $logger = new TestLogger();
    $factory = NULL;
    $channel = NULL;
    $loggers = (static function () use (&$factory, &$channel, $logger, $channelName): \Generator {
      $channel = $factory->get($channelName);
      yield $logger;
    })();
    $factory = new LoggerChannelFactory(
      new RequestStack(),
      $this->createStub(AccountInterface::class),
      $loggers,
    );
    $factory->get('test');
    $this->assertSame($factory->get($channelName), $channel);
    $channel->notice('Test message');
    $this->assertCount(1, $logger->records);
  }

  /**
   * Tests adding loggers before and after creating channels.
   */
  public function testAddLogger(): void {
    $injected = new TestLogger();
    $before = new TestLogger();
    $after = new TestLogger();
    $factory = new LoggerChannelFactory(
      new RequestStack(),
      $this->createStub(AccountInterface::class),
      [$injected],
    );
    $factory->addLogger($before, 10);
    $channel = $factory->get('test');
    $channel->notice('First message');
    $factory->addLogger($after, -10);
    $channel->notice('Second message');
    $factory->get('another')->notice('Third message');
    $this->assertCount(3, $injected->records);
    $this->assertCount(3, $before->records);
    $this->assertCount(2, $after->records);
  }

}

/**
 * Call to test a logger channel class with no constructor.
 */
class LoggerChannelWithoutConstructor extends LoggerChannelFactory {

  public function __construct() {}

}
