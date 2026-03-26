<?php

declare(strict_types=1);

namespace Drupal\ckeditor5\Plugin\ConfigAction;

use Drupal\ckeditor5\Plugin\CKEditor5PluginManagerInterface;
use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Config action to set settings for a specific CKEditor 5 plugin.
 *
 * This allows setting configuration for a single plugin in an editor config
 * entity without overriding all plugin settings.
 *
 * Example usage in a recipe:
 * @code
 * config:
 *   actions:
 *     editor.editor.content_format:
 *       setPluginSettings:
 *         plugin: ckeditor5_alignment
 *         settings:
 *           enabled_alignments:
 *             - center
 *             - left
 *             - right
 * @endcode
 */
#[ConfigAction(
  id: 'editor:setPluginSettings',
  admin_label: new TranslatableMarkup('Set CKEditor 5 plugin settings'),
  entity_types: ['editor'],
)]
final class SetPluginSettings implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly CKEditor5PluginManagerInterface $pluginManager,
    private readonly string $pluginId,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get(CKEditor5PluginManagerInterface::class),
      $plugin_id,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param string $configName
   *   The name of the editor config entity.
   * @param mixed $value
   *   An array with keys:
   *   - plugin: (string) The CKEditor 5 plugin ID whose settings to set.
   *   - settings: (array) The settings to apply to the plugin.
   *   - strict: (bool, optional) If TRUE, an exception is thrown when the
   *     plugin is not already present in the editor's settings. Defaults to
   *     FALSE, which falls back to the plugin's default configuration.
   *
   * @throws \Drupal\Core\Config\Action\ConfigActionException
   *   Thrown if the editor is not using CKEditor 5, the value is invalid, the
   *   plugin does not exist, the plugin is not configurable, or strict mode is
   *   enabled and the plugin is not already configured in the editor.
   */
  public function apply(string $configName, mixed $value): void {
    $editor = $this->configManager->loadConfigEntityByName($configName);
    assert($editor instanceof EditorInterface);

    if ($editor->getEditor() !== 'ckeditor5') {
      throw new ConfigActionException(sprintf('The %s config action only works with editors that use CKEditor 5.', $this->pluginId));
    }

    if (!is_array($value) || !isset($value['plugin']) || !isset($value['settings'])) {
      throw new ConfigActionException(sprintf('The %s config action requires an array with "plugin" and "settings" keys.', $this->pluginId));
    }

    $ck_plugin_id = $value['plugin'];
    $settings = $value['settings'];
    $strict = $value['strict'] ?? FALSE;

    if (!is_string($ck_plugin_id)) {
      throw new ConfigActionException(sprintf('The %s config action requires "plugin" to be a string.', $this->pluginId));
    }

    if (!is_array($settings)) {
      throw new ConfigActionException(sprintf('The %s config action requires "settings" to be an array.', $this->pluginId));
    }

    $definitions = $this->pluginManager->getDefinitions();
    if (!isset($definitions[$ck_plugin_id])) {
      throw new ConfigActionException(sprintf('The %s config action cannot set settings for "%s" because that CKEditor 5 plugin does not exist.', $this->pluginId, $ck_plugin_id));
    }

    /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $definition */
    $definition = $definitions[$ck_plugin_id];
    if (!$definition->isConfigurable()) {
      throw new ConfigActionException(sprintf('The %s config action cannot set settings for "%s" because that CKEditor 5 plugin is not configurable.', $this->pluginId, $ck_plugin_id));
    }

    $editor_settings = $editor->getSettings();
    $plugin_already_configured = array_key_exists($ck_plugin_id, $editor_settings['plugins']);

    if ($strict && !$plugin_already_configured) {
      throw new ConfigActionException(sprintf('The %s config action cannot set settings for "%s" because that plugin is not configured in the editor and strict mode is enabled.', $this->pluginId, $ck_plugin_id));
    }

    // Merge the provided settings on top of existing or default settings.
    /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface $plugin_instance */
    $plugin_instance = $this->pluginManager->getPlugin($ck_plugin_id, NULL);
    $existing = $editor_settings['plugins'][$ck_plugin_id] ?? $plugin_instance->defaultConfiguration();
    $editor_settings['plugins'][$ck_plugin_id] = array_replace_recursive($existing, $settings);

    $editor->setSettings($editor_settings)->save();
  }

}
