<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper;
use Drupal\Core\DependencyInjection\Container;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Cache\ExtraFieldBlockCacheTagInvalidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;

/**
 * Tests the extra field block cache tag invalidator.
 */
#[CoversClass(ExtraFieldBlockCacheTagInvalidator::class)]
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class ExtraFieldBlockCacheTagInvalidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_logger_test',
    'layout_discovery',
  ];

  /**
   * Tests that a logger depending on cache_tags.invalidator does not loop.
   */
  public function testNoCircularReference(): void {
    // Use a dumped container like a real site, starting with the default logger
    // channel.
    $container = new Container((new OptimizedPhpArrayDumper($this->container))->getArray());
    $this->assertInstanceOf(LoggerInterface::class, $container->get('logger.channel.default'));
  }

}
