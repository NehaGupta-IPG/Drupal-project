<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Logger;

use ColinODell\PsrTestLogger\TestLogger;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests lazy logger service initialization.
 */
#[Group('Logger')]
#[RunTestsInSeparateProcesses]
class LoggerChannelFactoryTest extends KernelTestBase {

  /**
   * The order in which logger services are initialized.
   *
   * @var string[]
   */
  protected static array $initializedLoggers = [];

  /**
   * Tests that a logger can depend on the logger channel factory.
   */
  public function testLazyLogger(): void {
    $factory = $this->container->get('logger.factory');
    $this->assertSame([], self::$initializedLoggers);
    $channel = $factory->get('test');
    $this->assertSame(['high', 'equal', 'default', 'low'], self::$initializedLoggers);
    $channel->notice('Test message');
    foreach (self::$initializedLoggers as $id) {
      $logger = $this->container->get('logger.test.' . $id);
      $this->assertCount(1, $logger->records);
      $this->assertSame('test', $logger->records[0]['context']['channel']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    foreach (['low' => -20, 'high' => 20, 'equal' => 20, 'default' => 0] as $id => $priority) {
      $container->register('logger.test.' . $id, TestLogger::class)
        ->setFactory([self::class, 'createLogger'])
        ->addArgument(new Reference('logger.factory'))
        ->addArgument($id)
        ->addTag('logger', ['priority' => $priority]);
    }
  }

  /**
   * Creates a logger with a dependency on the logger channel factory.
   */
  public static function createLogger(LoggerChannelFactoryInterface $factory, string $id): TestLogger {
    $factory->get('test');
    self::$initializedLoggers[] = $id;
    return new TestLogger();
  }

}
