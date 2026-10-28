<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Plugin\Discovery;

use Drupal\Component\Plugin\Derivative\DeriverInterface;
use Drupal\Component\Plugin\Discovery\StaticDiscovery;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the container-aware plugin deriver.
 */
#[CoversClass(ContainerDerivativeDiscoveryDecorator::class)]
#[Group('Plugin')]
final class ContainerDerivativeDiscoveryDecoratorTest extends UnitTestCase {

  /**
   * Tests that derivers' cache tags are collected by this decorator.
   */
  public function testCacheTagsAreCollected(): void {
    $decorated = new StaticDiscovery();
    $decorated->setDefinition('plugin1', [
      'id' => 'plugin1',
      'deriver' => CacheableMockDeriver::class,
    ]);
    $decorated->setDefinition('plugin2', [
      'id' => 'plugin2',
      'deriver' => CacheableMockDeriver::class,
    ]);

    $deriver = new ContainerDerivativeDiscoveryDecorator($decorated);
    $definitions = $deriver->getDefinitions();
    $this->assertArrayHasKey('plugin1:derived', $definitions);
    $this->assertArrayHasKey('plugin2:derived', $definitions);
    $this->assertSame(['plugin1', 'plugin2'], $deriver->getCacheTags());
    // The deriver's cache contexts and max age should be ignored.
    $this->assertSame([], $deriver->getCacheContexts());
    $this->assertSame(Cache::PERMANENT, $deriver->getCacheMaxAge());
  }

}

/**
 * A mock deriver that provides cache metadata.
 */
class CacheableMockDeriver implements DeriverInterface, CacheableDependencyInterface {

  public function __construct(private readonly string $basePluginId) {}

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return [$this->basePluginId];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return 42;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    return $base_plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    return ['derived' => $base_plugin_definition];
  }

}
