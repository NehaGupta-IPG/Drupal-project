<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Plugin\Attribute;

use Composer\Autoload\ClassLoader;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Plugin\Attribute\PluginPropertyInterface;
use Drupal\Component\Plugin\Discovery\AttributeClassDiscovery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests plugin property attributes against the plugin discovery file cache.
 *
 * The namespaces a discovery scans stand in for the installed modules, as they
 * do in \Drupal\Core\Plugin\Discovery\AttributeClassDiscovery, where they come
 * from the installed modules by way of the 'container.namespaces' parameter. An
 * attribute class in a namespace that is not among them is treated as belonging
 * to a module that is not installed.
 *
 * The plugin classes have to be discovered by scanning a directory, so they are
 * fixture files. The attribute classes are only ever reached by name, so they
 * are declared at the bottom of this file.
 *
 * @see \com\example\PluginProperty\Plugin\PropertyDiscoveryTest1
 */
#[CoversClass(AttributeClassDiscovery::class)]
#[Group('Attribute')]
#[RunTestsInSeparateProcesses]
class AttributeClassDiscoveryPluginPropertyTest extends TestCase {

  /**
   * The namespace of the attribute classes declared in this file.
   */
  protected const string ATTRIBUTE_NAMESPACE = 'Drupal\Tests\Component\Plugin\Attribute';

  /**
   * The directory holding the plugin classes.
   */
  protected string $pluginPath;

  /**
   * The file the property attributes are declared on.
   */
  protected string $pluginFilePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure FileCacheFactory::DISABLE_CACHE is *not* set, since the point of
    // this test is the interaction with the file cache.
    FileCacheFactory::setConfiguration([]);
    FileCacheFactory::setPrefix('prefix');

    $fixtures = __DIR__ . '/../../../../../fixtures';
    $this->pluginPath = $fixtures . '/plugin_property/Plugin';
    $this->pluginFilePath = $this->pluginPath . '/PropertyDiscoveryTest1.php';

    $class_loader = new ClassLoader();
    $class_loader->addPsr4('com\\example\\PluginProperty\\', $fixtures . '/plugin_property');
    $class_loader->register(TRUE);
  }

  /**
   * Returns a discovery, optionally scanning the attribute namespace.
   *
   * The attribute classes are always declared, exactly as the classes of an
   * uninstalled module on disk are always autoloadable. What decides whether
   * they are applied is whether their namespace is one the discovery knows
   * about. No directory is given for it, because a plugin namespace of an
   * installed module routinely has no directory on disk.
   */
  protected function getDiscovery(bool $optional_provider_installed): AttributeClassDiscovery {
    $namespaces = ['com\example\PluginProperty\Plugin' => [$this->pluginPath]];
    if ($optional_provider_installed) {
      $namespaces[self::ATTRIBUTE_NAMESPACE] = [];
    }
    return new AttributeClassDiscovery($namespaces);
  }

  /**
   * Returns the file cache entry for the plugin class file.
   */
  protected function getCacheItem(AttributeClassDiscovery $discovery): ?array {
    $file_cache = (new \ReflectionProperty($discovery, 'fileCache'))->getValue($discovery);
    return $file_cache->get($this->pluginFilePath);
  }

  /**
   * Tests that property attributes of an uninstalled provider are not applied.
   */
  public function testPropertiesNotAppliedWhenProviderIsNotScanned(): void {
    $definitions = $this->getDiscovery(FALSE)->getDefinitions();

    $this->assertEquals([
      'id' => 'property_discovery_test_1',
      'class' => 'com\example\PluginProperty\Plugin\PropertyDiscoveryTest1',
    ], $definitions['property_discovery_test_1']);
    $this->assertEquals([
      'id' => 'property_discovery_test_2',
      'class' => 'com\example\PluginProperty\Plugin\PropertyDiscoveryTest2',
    ], $definitions['property_discovery_test_2']);
  }

  /**
   * Tests which attributes of a scanned provider are applied.
   */
  public function testPropertiesAppliedWhenProviderIsScanned(): void {
    $definitions = $this->getDiscovery(TRUE)->getDefinitions();

    // NotAProperty is in the scanned namespace but does not implement
    // PluginPropertyInterface, and NeverAvailable is not declared at all.
    // Neither sets anything, and neither causes an error.
    $this->assertEquals([
      'id' => 'property_discovery_test_1',
      'class' => 'com\example\PluginProperty\Plugin\PropertyDiscoveryTest1',
      'optional_property' => 'optional value',
      'second_optional_property' => TRUE,
    ], $definitions['property_discovery_test_1']);
  }

  /**
   * Tests that the cached definition never has property values applied.
   */
  public function testCacheItemHoldsUnresolvedPropertyAttributes(): void {
    $discovery = $this->getDiscovery(TRUE);
    $discovery->getDefinitions();

    $cache_item = $this->getCacheItem($discovery);
    $this->assertNotNull($cache_item);
    // The cached definition must not contain the property values, otherwise
    // uninstalling the provider would leave them in place until the file
    // changes.
    $this->assertEquals([
      'id' => 'property_discovery_test_1',
      'class' => 'com\example\PluginProperty\Plugin\PropertyDiscoveryTest1',
    ], unserialize($cache_item['content']));

    // Every attribute other than the plugin definition attribute is cached
    // unresolved, including the one that is not declared anywhere. Which of
    // them apply is decided on each request, so the cache entry does not
    // depend on the installed modules.
    $this->assertEquals([
      [
        'class' => OptionalProperty::class,
        'arguments' => ['optional_property', 'optional value'],
      ],
      [
        'class' => OptionalProperty::class,
        'arguments' => ['key' => 'second_optional_property', 'value' => TRUE],
      ],
      [
        'class' => NotAProperty::class,
        'arguments' => ['This attribute does not implement PluginPropertyInterface.'],
      ],
      [
        'class' => 'com\missing\Attribute\NeverAvailable',
        'arguments' => ['never', 'available'],
      ],
    ], unserialize($cache_item['property_attributes']));

    // A plugin with no property attributes gets no cache key for them.
    $file_cache = (new \ReflectionProperty($discovery, 'fileCache'))->getValue($discovery);
    $this->assertArrayNotHasKey('property_attributes', $file_cache->get($this->pluginPath . '/PropertyDiscoveryTest2.php'));
  }

  /**
   * Tests that properties are resolved against a warm file cache.
   *
   * This is the case the file cache made hard: the file cache is keyed on the
   * file path and only invalidated when the file changes, so installing or
   * uninstalling the provider cannot invalidate it.
   */
  public function testPropertiesAreResolvedAgainstAWarmFileCache(): void {
    // Warm the file cache while the provider is not scanned.
    $cold = $this->getDiscovery(FALSE);
    $this->assertArrayNotHasKey('optional_property', $cold->getDefinitions()['property_discovery_test_1']);

    // Mark the cached definition, so that a definition built from the cache
    // can be told apart from one built by parsing the class again.
    $file_cache = (new \ReflectionProperty($cold, 'fileCache'))->getValue($cold);
    $cache_item = $this->getCacheItem($cold);
    $this->assertNotNull($cache_item, 'The plugin class file is in the file cache.');
    $cache_item['content'] = serialize(['id' => 'property_discovery_test_1', 'from_cache' => TRUE]);
    $file_cache->set($this->pluginFilePath, $cache_item);

    // Install the provider. The properties are applied on top of the marked
    // cache entry, so no re-parsing was needed to pick up the attribute.
    $definitions = $this->getDiscovery(TRUE)->getDefinitions();
    $this->assertSame([
      'id' => 'property_discovery_test_1',
      'from_cache' => TRUE,
      'optional_property' => 'optional value',
      'second_optional_property' => TRUE,
    ], $definitions['property_discovery_test_1']);

    // Uninstall it again. The properties must go away, which they can only do
    // if applying them above left the cached definition alone.
    $definitions = $this->getDiscovery(FALSE)->getDefinitions();
    $this->assertSame([
      'id' => 'property_discovery_test_1',
      'from_cache' => TRUE,
    ], $definitions['property_discovery_test_1']);
  }

}

/**
 * A plugin property attribute standing in for one an optional module provides.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class OptionalProperty implements PluginPropertyInterface {

  public function __construct(
    public readonly string $key,
    public readonly mixed $value,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addToDefinition(string $id, array|object $definition): array {
    $definition[$this->key] = $this->value;
    return $definition;
  }

}

/**
 * An attribute that is available but does not set a plugin property.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NotAProperty {

  public function __construct(
    public readonly string $note = '',
  ) {}

}
