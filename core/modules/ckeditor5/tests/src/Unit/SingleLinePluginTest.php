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
 * Tests the single-line CKEditor 5 plugin.
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
      'ckeditor5' => [
        'plugins' => [
          'list.List',
          'list.ListUI',
        ],
      ],
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
      'ckeditor5' => [
        'plugins' => [
          'basicStyles.Bold',
        ],
      ],
      'drupal' => [
        'toolbar_items' => [
          'bold' => ['label' => 'Bold'],
        ],
        'is_inline' => TRUE,
      ],
    ]);

    $plugin_manager = $this->createStub(CKEditor5PluginManagerInterface::class);
    $plugin_manager->method('getEnabledDefinitions')
      ->willReturn([
        'ckeditor5_list' => $definition_block,
        'ckeditor5_bold' => $definition_inline,
      ]);

    $editor = $this->createStub(EditorInterface::class);

    $plugin = new SingleLine([], 'ckeditor5_singleLine', $definition_block, $plugin_manager);

    $dynamic_config = $plugin->getDynamicPluginConfig([], $editor);

    $this->assertArrayHasKey('drupalSingleLine', $dynamic_config);
    $this->assertSame(
      ['bulletedList', 'numberedList'],
      $dynamic_config['drupalSingleLine']['blockToolbarItems']
    );
    $this->assertSame(
      ['list.List', 'list.ListUI'],
      $dynamic_config['drupalSingleLine']['blockCKEditor5Plugins']
    );
  }

}
