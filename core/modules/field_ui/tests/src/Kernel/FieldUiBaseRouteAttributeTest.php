<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Kernel;

use Drupal\Component\FileCache\FileCache;
use Drupal\field_ui\Attribute\FieldUiBaseRoute;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the FieldUiBaseRoute attribute across installing and uninstalling.
 *
 * The plugin discovery file cache is keyed on the plugin class file path and
 * is only invalidated when that file changes. Installing or uninstalling
 * field_ui cannot invalidate it, so the property has to be resolved on every
 * request rather than baked into the cached definition.
 */
#[CoversClass(FieldUiBaseRoute::class)]
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldUiBaseRouteAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'system', 'field', 'text'];

  /**
   * Tests that the property follows the installed state of field_ui.
   */
  public function testFieldUiBaseRouteFollowsInstalledState(): void {
    $node_class_file = $this->root . '/core/modules/node/src/Entity/Node.php';

    // field_ui is not installed, so the property it owns is not set.
    $this->assertNull($this->getNodeFieldUiBaseRoute());

    // Discovery has run, so the plugin class file is now in the file cache,
    // with the attribute stored unresolved.
    $cache_item = $this->getPluginDiscoveryCacheItem($node_class_file);
    $this->assertNotNull($cache_item, 'The node entity class is in the plugin discovery file cache.');
    $this->assertContains(FieldUiBaseRoute::class, array_column(unserialize($cache_item['property_attributes']), 'class'));
    $this->assertNull(unserialize($cache_item['content'])->get('field_ui_base_route'), 'The cached definition does not have the property applied.');

    $this->enableModules(['field_ui']);
    $this->assertSame('entity.node_type.edit_form', $this->getNodeFieldUiBaseRoute());

    $this->disableModules(['field_ui']);
    $this->assertNull($this->getNodeFieldUiBaseRoute());
  }

  /**
   * Tests that applying the property does not leak into the cached definition.
   *
   * Entity type definitions are objects, so the attribute modifies the one it
   * is given in place. When the class is parsed while field_ui is installed,
   * that must not be the object the file cache holds, or the property would
   * survive uninstalling field_ui until the class file changes.
   */
  public function testPropertyIsNotCachedWhenParsedWhileInstalled(): void {
    $node_class_file = $this->root . '/core/modules/node/src/Entity/Node.php';

    // Discard the file cache, so that installing field_ui is followed by the
    // entity class being parsed again rather than read back from the cache.
    $this->enableModules(['field_ui']);
    FileCache::reset();
    $this->assertSame('entity.node_type.edit_form', $this->getNodeFieldUiBaseRoute());

    $cache_item = $this->getPluginDiscoveryCacheItem($node_class_file);
    $this->assertNotNull($cache_item, 'The node entity class is in the plugin discovery file cache.');
    $this->assertNull(unserialize($cache_item['content'])->get('field_ui_base_route'), 'The cached definition does not have the property applied.');

    $this->disableModules(['field_ui']);
    $this->assertNull($this->getNodeFieldUiBaseRoute());
  }

  /**
   * Returns the 'field_ui_base_route' property of the node entity type.
   */
  protected function getNodeFieldUiBaseRoute(): ?string {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->clearCachedDefinitions();
    return $entity_type_manager->getDefinition('node')->get('field_ui_base_route');
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
