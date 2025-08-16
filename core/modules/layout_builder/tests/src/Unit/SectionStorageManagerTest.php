<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Unit;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Component\Plugin\Factory\FactoryInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextHandler;
use Drupal\Core\Plugin\Context\ContextInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageDefinition;
use Drupal\layout_builder\SectionStorage\SectionStorageManager;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\layout_builder\SectionStorage\SectionStorageManager
 *
 * @group layout_builder
 */
class SectionStorageManagerTest extends UnitTestCase {

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManager
   */
  protected $manager;

  /**
   * The plugin.
   *
   * @var \Drupal\layout_builder\SectionStorageInterface
   */
  protected $plugin;

  /**
   * The plugin discovery.
   *
   * @var \Drupal\Component\Plugin\Discovery\DiscoveryInterface
   */
  protected $discovery;

  /**
   * The plugin factory.
   *
   * @var \Drupal\Component\Plugin\Factory\FactoryInterface
   */
  protected $factory;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $cache = $this->prophesize(CacheBackendInterface::class);
    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $this->contextHandler = new ContextHandler();
    $this->manager = new SectionStorageManager(new \ArrayObject(), $cache->reveal(), $module_handler->reveal(), $this->contextHandler);

    $this->discovery = $this->prophesize(DiscoveryInterface::class);
    $reflection_property = new \ReflectionProperty($this->manager, 'discovery');
    $reflection_property->setValue($this->manager, $this->discovery->reveal());

    $this->plugin = $this->prophesize(SectionStorageInterface::class);
    $this->factory = $this->prophesize(FactoryInterface::class);
    $this->factory->createInstance('the_plugin_id', [])->willReturn($this->plugin->reveal());
    $reflection_property = new \ReflectionProperty($this->manager, 'factory');
    $reflection_property->setValue($this->manager, $this->factory->reveal());
  }

  /**
   * @covers ::loadEmpty
   */
  public function testLoadEmpty(): void {
    $result = $this->manager->loadEmpty('the_plugin_id');
    $this->assertInstanceOf(SectionStorageInterface::class, $result);
    $this->assertSame($this->plugin->reveal(), $result);
  }

  /**
   * @covers ::load
   */
  public function testLoad(): void {
    $contexts = [
      'the_context' => $this->prophesize(ContextInterface::class)->reveal(),
    ];

    $this->plugin->getContextDefinitions()->willReturn([]);
    $this->plugin->getContextMapping()->willReturn([]);

    $result = $this->manager->load('the_plugin_id', $contexts);
    $this->assertSame($this->plugin->reveal(), $result);
  }

  /**
   * @covers ::load
   */
  public function testLoadNull(): void {
    $context = $this->prophesize(ContextInterface::class);
    $context->getContextDefinition()->willReturn(new ContextDefinition('boolean'));
    $contexts = [
      'the_context' => $context->reveal(),
    ];

    $context_definitions = [
      'my_string' => new ContextDefinition('string'),
    ];
    $this->plugin->getContextDefinitions()->willReturn($context_definitions);
    $this->plugin->getContextMapping()->willReturn([]);
    $this->plugin->getContext('my_string')->shouldNotBeCalled();

    $result = $this->manager->load('the_plugin_id', $contexts);
    $this->assertNull($result);
  }

  /**
   * @covers ::findDefinitions
   */
  public function testFindDefinitions(): void {
    $this->discovery->getDefinitions()->willReturn([
      'plugin1' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
      'plugin2' => (new SectionStorageDefinition(['weight' => -5]))->setClass(SectionStorageInterface::class),
      'plugin3' => (new SectionStorageDefinition(['weight' => -5]))->setClass(SectionStorageInterface::class),
      'plugin4' => (new SectionStorageDefinition(['weight' => 10]))->setClass(SectionStorageInterface::class),
    ]);

    $expected = [
      'plugin2',
      'plugin3',
      'plugin1',
      'plugin4',
    ];
    $result = $this->manager->getDefinitions();
    $this->assertSame($expected, array_keys($result));
  }

  /**
   * @covers ::findByContext
   *
   * @dataProvider providerTestFindByContext
   *
   * @param bool $plugin_is_applicable
   *   The result for the plugin's isApplicable() method to return.
   */
  public function testFindByContext($plugin_is_applicable): void {
    $cacheability = new CacheableMetadata();
    $contexts = [
      'foo' => new Context(new ContextDefinition('foo')),
    ];
    $definitions = [
      'no_access' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
      'missing_contexts' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
      'provider_access' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
    ];
    $this->discovery->getDefinitions()->willReturn($definitions);

    $provider_access = $this->prophesize(SectionStorageInterface::class);
    $provider_access->isApplicable($cacheability)->willReturn($plugin_is_applicable);
    $provider_access->getContextDefinitions()->willReturn([]);
    $provider_access->getContextMapping()->willReturn([]);
    $provider_access->getContext(Argument::any())->shouldNotBeCalled();

    $no_access = $this->prophesize(SectionStorageInterface::class);
    $no_access->isApplicable($cacheability)->willReturn(FALSE);
    $no_access->getContextDefinitions()->willReturn([]);
    $no_access->getContextMapping()->willReturn([]);
    $no_access->getContext(Argument::any())->shouldNotBeCalled();

    $missing_contexts = $this->prophesize(SectionStorageInterface::class);
    $missing_contexts->isApplicable(Argument::cetera())->shouldNotBeCalled();
    $missing_contexts->getContextDefinitions()->willReturn(['bool' => new ContextDefinition('boolean')]);
    $missing_contexts->getContextMapping()->willReturn([]);
    $missing_contexts->getContext(Argument::any())->shouldNotBeCalled();

    $this->factory->createInstance('no_access', [])->willReturn($no_access->reveal());
    $this->factory->createInstance('missing_contexts', [])->willReturn($missing_contexts->reveal());
    $this->factory->createInstance('provider_access', [])->willReturn($provider_access->reveal());

    $result = $this->manager->findByContext($contexts, $cacheability);
    if ($plugin_is_applicable) {
      $this->assertSame($provider_access->reveal(), $result);
    }
    else {
      $this->assertNull($result);
    }
  }

  /**
   * Provides test data for ::testFindByContext().
   */
  public static function providerTestFindByContext() {
    // Data provider values are:
    // - the result for the plugin's isApplicable() method to return.
    $data = [];
    $data['plugin access: true'] = [TRUE];
    $data['plugin access: false'] = [FALSE];
    return $data;
  }

  /**
   * @covers ::findByContext
   */
  public function testFindByContextCacheableSectionStorage(): void {
    $cacheability = new CacheableMetadata();
    $contexts = [
      'foo' => new Context(new ContextDefinition('foo')),
    ];

    $definitions = [
      'first' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
      'second' => (new SectionStorageDefinition())->setClass(SectionStorageInterface::class),
    ];
    $this->discovery->getDefinitions()->willReturn($definitions);

    // Create a plugin that has cacheability info itself as a cacheable object
    // and from within ::isApplicable() but is not applicable.
    $first_plugin = $this->prophesize(SectionStorageInterface::class);
    $first_plugin->willImplement(CacheableDependencyInterface::class);
    $first_plugin->getCacheContexts()->shouldNotBeCalled();
    $first_plugin->getCacheTags()->shouldNotBeCalled();
    $first_plugin->getCacheMaxAge()->shouldNotBeCalled();
    $first_plugin->getContextDefinitions()->willReturn([]);
    $first_plugin->getContextMapping()->willReturn([]);
    $first_plugin->isApplicable($cacheability)->will(function ($arguments) {
      $arguments[0]->addCacheTags(['first_plugin']);
      return FALSE;
    });

    // Create a plugin that adds cacheability info from within ::isApplicable()
    // and is applicable.
    $second_plugin = $this->prophesize(SectionStorageInterface::class);
    $second_plugin->isApplicable($cacheability)->will(function ($arguments) {
      $arguments[0]->addCacheTags(['second_plugin']);
      return TRUE;
    });
    $second_plugin->getContextDefinitions()->willReturn([]);
    $second_plugin->getContextMapping()->willReturn([]);

    $this->factory->createInstance('first', [])->willReturn($first_plugin->reveal());
    $this->factory->createInstance('second', [])->willReturn($second_plugin->reveal());

    $result = $this->manager->findByContext($contexts, $cacheability);
    $this->assertSame($second_plugin->reveal(), $result);
    $this->assertSame(['first_plugin', 'second_plugin'], $cacheability->getCacheTags());
  }

}
