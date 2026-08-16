<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Kernel;

use Drupal\Component\FileCache\FileCache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\navigation\Attribute\AllowInNavigation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AllowInNavigation attribute across installing and uninstalling.
 *
 * The navigation_test_block module does not depend on navigation, so it stands
 * in for a module that opts one of its blocks into the navigation without
 * requiring it.
 */
#[CoversClass(AllowInNavigation::class)]
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class AllowInNavigationAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'block', 'navigation_test_block'];

  /**
   * Tests that the property follows the installed state of navigation.
   */
  public function testAllowInNavigationFollowsInstalledState(): void {
    $block_class_file = $this->root . '/core/modules/navigation/tests/navigation_test_block/src/Plugin/Block/NavigationTestBlock.php';

    // The navigation module is not installed, so the property it owns is not
    // set.
    $this->assertArrayNotHasKey('allow_in_navigation', $this->getTestBlockDefinition());

    // Discovery has run, so the plugin class file is now in the file cache,
    // with the attribute stored unresolved.
    $cache_item = $this->getPluginDiscoveryCacheItem($block_class_file);
    $this->assertNotNull($cache_item, 'The test block class is in the plugin discovery file cache.');
    $this->assertSame([
      [
        'class' => AllowInNavigation::class,
        'arguments' => [],
      ],
    ], unserialize($cache_item['property_attributes']));
    $this->assertArrayNotHasKey('allow_in_navigation', unserialize($cache_item['content']));

    $this->enableModules(['file', 'layout_builder', 'navigation']);
    $this->assertTrue($this->getTestBlockDefinition()['allow_in_navigation']);
    // A block navigation provides itself carries the attribute too.
    $this->assertTrue($this->getBlockDefinition('navigation_user')['allow_in_navigation']);

    $this->disableModules(['navigation']);
    $this->assertArrayNotHasKey('allow_in_navigation', $this->getTestBlockDefinition());
  }

  /**
   * Returns the definition of the test block.
   */
  protected function getTestBlockDefinition(): array {
    return $this->getBlockDefinition('navigation_test');
  }

  /**
   * Returns a block plugin definition with the definition cache cleared.
   */
  protected function getBlockDefinition(string $plugin_id): array {
    $block_manager = $this->container->get('plugin.manager.block');
    $block_manager->clearCachedDefinitions();
    return $block_manager->getDefinition($plugin_id);
  }

  /**
   * Returns the plugin discovery file cache entry for a class file.
   *
   * The collection name depends on the plugin type, so every attribute
   * discovery collection is searched for the file.
   */
  protected function getPluginDiscoveryCacheItem(string $path): ?array {
    $realpath = realpath($path);
    $cached = (new \ReflectionProperty(FileCache::class, 'cached'))->getValue();
    foreach ($cached as $cid => $item) {
      if (str_contains($cid, ':attribute_discovery:') && str_ends_with($cid, ':' . $realpath)) {
        return $item['data'];
      }
    }
    return NULL;
  }

}
