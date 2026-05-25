<?php

declare(strict_types = 1);

namespace Drupal\ckeditor5\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Single line helper plugin.
 *
 * Computes dynamic configuration for the single-line editor behavior.
 *
 * @internal
 *   Plugin classes are internal.
 */
class SingleLine extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CKEditor5PluginManagerInterface $pluginManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Computes the single-line configuration for an editor.
   *
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor configuration.
   * @param \Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface $plugin_manager
   *   The CKEditor 5 plugin manager.
   *
   * @return array
   *   The configuration array with 'blockCKEditor5Plugins' and
   *   'blockToolbarItems' keys.
   */
  public static function computeConfig(EditorInterface $editor, CKEditor5PluginManagerInterface $plugin_manager): array {
    // Find all enabled plugins that are NOT inline formatting plugins.
    $enabled_definitions = $plugin_manager->getEnabledDefinitions($editor);
    $incompatible_plugins = [];
    foreach ($enabled_definitions as $plugin_id => $definition) {
      if (!$definition->isInline()) {
        $incompatible_plugins[$plugin_id] = $definition;
      }
    }

    // Collect CKEditor 5 plugin class names and toolbar items for the
    // incompatible plugins.
    $block_ckeditor5_plugins = [];
    $block_toolbar_items = [];
    foreach ($incompatible_plugins as $plugin_id => $definition) {
      // Get CKEditor 5 plugin class names (e.g., 'drupalMedia.DrupalMedia').
      foreach ($definition->getCKEditor5Plugins() as $ckeditor5_plugin) {
        $block_ckeditor5_plugins[] = $ckeditor5_plugin;
      }
      foreach ($definition->getToolbarItems() as $item_name => $item_definition) {
        $block_toolbar_items[] = $item_name;
      }
    }
    $block_ckeditor5_plugins = array_values(array_unique($block_ckeditor5_plugins));
    $block_toolbar_items = array_values(array_unique($block_toolbar_items));

    return [
      'blockCKEditor5Plugins' => $block_ckeditor5_plugins,
      'blockToolbarItems' => $block_toolbar_items,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $config = self::computeConfig($editor, $this->pluginManager);

    // Expose configuration for filtering plugins and toolbar items.
    // @see core/modules/ckeditor5/js/ckeditor5.js
    return [
      'drupalSingleLine' => $config,
    ];
  }

}
