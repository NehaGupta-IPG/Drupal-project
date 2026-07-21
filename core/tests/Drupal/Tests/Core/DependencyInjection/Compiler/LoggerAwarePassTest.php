<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\Compiler\LoggerAwarePass;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Tests Drupal\Core\DependencyInjection\Compiler\LoggerAwarePass.
 */
#[CoversClass(LoggerAwarePass::class)]
#[Group('DependencyInjection')]
class LoggerAwarePassTest extends UnitTestCase {

  /**
   * Tests that the exception names the offending service id and class.
   *
   * @legacy-covers ::process
   */
  public function testNotLoggerAwareException(): void {
    $container = new ContainerBuilder();

    $definition = new Definition(\stdClass::class);
    $definition->addTag('logger_aware');
    $definition->addTag('_provider', ['provider' => 'test_provider']);
    $definition->setPublic(TRUE);
    $container->setDefinition('test_provider.not_logger_aware', $definition);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Service "test_provider.not_logger_aware" (stdClass) must implement interface "Psr\Log\LoggerAwareInterface".');

    (new LoggerAwarePass())->process($container);
  }

}
