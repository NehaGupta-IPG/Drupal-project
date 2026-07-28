<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\Compiler\LoggerAwareInstanceTaggingPass;
use Drupal\Core\DependencyInjection\Compiler\LoggerAwarePass;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * End-to-end coverage of LoggerAwarePass, through a real compile().
 *
 * Test self::registerPipeline() must stay in sync with CoreServiceProvider.
 */
#[CoversClass(LoggerAwarePass::class)]
#[Group('DependencyInjection')]
class LoggerAwareEndToEndTest extends UnitTestCase {

  private function registerPipeline(ContainerBuilder $container): void {
    $container->registerForAutoconfiguration(LoggerAwareInterface::class)->addTag('logger_aware');
    $container->addCompilerPass(new LoggerAwareInstanceTaggingPass());
    $container->addCompilerPass(new LoggerAwarePass(), PassConfig::TYPE_AFTER_REMOVING);
  }

  public function testUndecoratedServiceIsWired(): void {
    $container = new ContainerBuilder();
    $this->registerPipeline($container);
    $container->register('logger.channel.test', LoggerAwareEndToEndChannelLogger::class)->setPublic(TRUE);
    $container->register('my.stub', LoggerAwareEndToEndStub::class)
      ->addTag('_provider', ['provider' => 'test'])
      ->setAutoconfigured(TRUE)
      ->setPublic(TRUE);

    $container->compile();

    $this->assertTrue($container->getDefinition('my.stub')->hasMethodCall('setLogger'));
  }

  /**
   * Real instance gets setLogger(), decorator doesn't, must not throw.
   */
  public function testDecorationByPlainClassDoesNotThrowAndWiresTheRealInstance(): void {
    $container = new ContainerBuilder();
    $this->registerPipeline($container);
    $container->register('logger.channel.test', LoggerAwareEndToEndChannelLogger::class)->setPublic(TRUE);
    $container->register('my.base', LoggerAwareEndToEndStub::class)
      ->addTag('_provider', ['provider' => 'test'])
      ->setAutoconfigured(TRUE)
      ->setPublic(TRUE);
    $decorator = $container->register('my.decorator', LoggerAwareEndToEndPlainDecorator::class)
      ->setPublic(TRUE)
      ->setDecoratedService('my.base');
    $decorator->setArguments([new Reference('.inner')]);

    $container->compile();

    $decoratorDefinition = $container->getDefinition('my.decorator');
    $this->assertFalse($decoratorDefinition->hasMethodCall('setLogger'));

    $baseId = 'my.decorator.inner';
    $this->assertTrue($container->hasDefinition($baseId), 'The real instance was inlined away / not addressable.');
    $this->assertTrue($container->getDefinition($baseId)->hasMethodCall('setLogger'));
  }

  /**
   * Must wire both instances in the chain, not just the outer one.
   */
  public function testMultiLayerChainWiresEveryLoggerAwareInstance(): void {
    $container = new ContainerBuilder();
    $this->registerPipeline($container);
    $container->register('logger.channel.test', LoggerAwareEndToEndChannelLogger::class)->setPublic(TRUE);
    $container->register('my.base', LoggerAwareEndToEndStub::class)
      ->addTag('_provider', ['provider' => 'test'])
      ->setAutoconfigured(TRUE)
      ->setPublic(TRUE);
    $middle = $container->register('my.middle', LoggerAwareEndToEndDecorator::class)
      ->addTag('_provider', ['provider' => 'test'])
      ->setAutoconfigured(TRUE)
      ->setPublic(TRUE)
      ->setDecoratedService('my.base');
    $middle->setArguments([new Reference('.inner')]);

    $container->compile();

    $this->assertTrue($container->getDefinition('my.middle')->hasMethodCall('setLogger'), 'The outer (middle) instance was not wired.');

    $baseId = 'my.middle.inner';
    $this->assertTrue($container->hasDefinition($baseId), 'The base instance was inlined away.');
    $this->assertTrue($container->getDefinition($baseId)->hasMethodCall('setLogger'), 'The base (inner) instance was not wired.');
  }

  public function testGenuineMisconfigurationStillThrows(): void {
    $container = new ContainerBuilder();
    $definition = new Definition(\stdClass::class);
    $definition->addTag('logger_aware');
    $definition->addTag('_provider', ['provider' => 'test']);
    $definition->setPublic(TRUE);
    $container->setDefinition('not_logger_aware', $definition);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Service "not_logger_aware" (stdClass) must implement interface "Psr\Log\LoggerAwareInterface".');

    (new LoggerAwarePass())->process($container);
  }

}

/**
 * Trivial logger aware stub.
 */
class LoggerAwareEndToEndStub implements LoggerAwareInterface {
  use LoggerAwareTrait;

}

/**
 * Trivial logger aware decorator.
 */
class LoggerAwareEndToEndPlainDecorator {

  public function __construct(
    public readonly object $decorated,
  ) {}

}

/**
 * Simple logger aware stub.
 */
class LoggerAwareEndToEndDecorator implements LoggerAwareInterface {
  use LoggerAwareTrait;

  public function __construct(
    public readonly object $decorated,
  ) {}

}

/**
 * Simple logger stub.
 */
class LoggerAwareEndToEndChannelLogger extends AbstractLogger {

  public function log($level, $message, array $context = []): void {}

}
