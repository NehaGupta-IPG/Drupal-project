<?php

declare(strict_types=1);

namespace Drupal\Tests\ckeditor5\Unit;

use Drupal\ckeditor5\Plugin\CKEditor5Plugin\SingleLine;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition;
use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\editor\EditorInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * @coversDefaultClass \Drupal\ckeditor5\Plugin\CKEditor5Plugin\SingleLine
 *
 * @internal
 */
#[Group('ckeditor5')]
class SingleLinePluginTest extends UnitTestCase {

  /**
   * Tests getDynamicPluginConfig().
   *
   * Ensures that toolbar items from enabled block-level plugins are exposed to
   * the JS plugin.
   */
  public function testGetDynamicPluginConfig(): void {
    $definition_block = new CKEditor5PluginDefinition([
      'id' => 'ckeditor5_list',
      'drupal' => [
        'toolbar_items' => [
          'bulletedList' => ['label' => 'Bulleted List'],
          'numberedList' => ['label' => 'Numbered List'],
        ],
        // is_inline defaults to FALSE, so no need to set it.
      ],
    ]);

    $definition_inline = new CKEditor5PluginDefinition([
      'id' => 'ckeditor5_bold',
      'drupal' => [
        'toolbar_items' => [
          'bold' => ['label' => 'Bold'],
        ],
        'is_inline' => TRUE,
      ],
    ]);

    $plugin_manager = $this->createMock(CKEditor5PluginManagerInterface::class);
    $plugin_manager->method('getEnabledDefinitions')
      ->willReturn([
        'ckeditor5_list' => $definition_block,
        'ckeditor5_bold' => $definition_inline,
      ]);

    $editor = $this->createMock(EditorInterface::class);

    $plugin = new SingleLine([], 'ckeditor5_singleLine', $definition_block);
    // Inject mocked plugin manager.
    $this->setProtectedProperty($plugin, 'pluginManager', $plugin_manager);

    $dynamic_config = $plugin->getDynamicPluginConfig([], $editor);

    $this->assertArrayHasKey('drupalSingleLine', $dynamic_config);
    $this->assertSame(
      ['bulletedList', 'numberedList'],
      $dynamic_config['drupalSingleLine']['blockToolbarItems']
    );
    $this->assertSame(
      ['bulletedList', 'numberedList'],
      $dynamic_config['drupalSingleLine']['blockCommands']
    );
  }

  /**
   * Helper to set a protected property for testing.
   */
  protected function setProtectedProperty(object $object, string $property, mixed $value): void {
    $ref = new \ReflectionObject($object);
    $prop = $ref->getProperty($property);
    $prop->setAccessible(TRUE);
    $prop->setValue($object, $value);
  }

}
