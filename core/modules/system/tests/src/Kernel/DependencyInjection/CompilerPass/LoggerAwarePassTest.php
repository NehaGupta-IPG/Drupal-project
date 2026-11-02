<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\DependencyInjection\CompilerPass;

use Drupal\Core\DependencyInjection\Compiler\LoggerAwarePass;
use Drupal\KernelTests\KernelTestBase;
use Drupal\logger_aware_test\LoggerAwareMiddleDecorator;
use Drupal\logger_aware_test\LoggerAwareStub;
use Drupal\logger_aware_test\LoggerAwareStubDecorator;
use Drupal\logger_aware_test\LoggerStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;

/**
 * Tests the logger aware compiler pass.
 */
#[CoversClass(LoggerAwarePass::class)]
#[Group('system')]
#[RunTestsInSeparateProcesses]
class LoggerAwarePassTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'logger_aware_test',
  ];

  /**
   * Tests that the logger aware compiler pass works.
   *
   * @legacy-covers ::process
   */
  public function testLoggerAwarePass(): void {
    $container = $this->container;
    $logger = $container->get('logger.channel.logger_aware_test');
    $this->assertInstanceOf(LoggerInterface::class, $logger);
    $logger_aware_stub = $container->get('logger_aware_test.logger_aware_stub');
    $this->assertInstanceOf(LoggerAwareStub::class, $logger_aware_stub);
    $this->assertSame($logger, $logger_aware_stub->getLogger());
  }

  /**
   * Tests that existing loggers are not overwritten.
   *
   * @legacy-covers ::process
   */
  public function testExistingLogger(): void {
    $container = $this->container;
    $logger_aware_stub = $container->get('logger_aware_test.logger_aware_existing');
    $logger = $logger_aware_stub->getLogger();
    $this->assertInstanceOf(LoggerStub::class, $logger);
  }

  /**
   * Tests that a decorator not itself logger aware still gets wired up.
   *
   * @legacy-covers ::process
   */
  public function testDecoratedLoggerAware(): void {
    $container = $this->container;
    $logger = $container->get('logger.channel.logger_aware_test');
    $decorator = $container->get('logger_aware_test.logger_aware_decorated_decorator');
    $this->assertInstanceOf(LoggerAwareStubDecorator::class, $decorator);
    $this->assertSame($logger, $decorator->getDecorated()->getLogger());
  }

  /**
   * Tests a chain where more than one instance is logger aware.
   *
   * The outer decorator is found directly by the tag (it implements
   * LoggerAwareInterface itself), which must not stop the pass from also
   * wiring up the inner, separately logger-aware, decorated instance.
   *
   * @legacy-covers ::process
   */
  public function testMultipleLoggerAwareInChain(): void {
    $container = $this->container;
    $logger = $container->get('logger.channel.logger_aware_test');
    $middle = $container->get('logger_aware_test.logger_aware_multi_middle');
    $this->assertInstanceOf(LoggerAwareMiddleDecorator::class, $middle);
    $this->assertSame($logger, $middle->getLogger());
    $this->assertSame($logger, $middle->getDecorated()->getLogger());
  }

}
