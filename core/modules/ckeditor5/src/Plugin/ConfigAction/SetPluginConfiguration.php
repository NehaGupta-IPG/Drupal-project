<?php

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

#[ConfigAction(
  id: 'editor:setPluginConfiguration',
  admin_label: new TranslatableMarkup('Sets CKEditor5 plugin configuration'),
  entity_types: ['editor'],
)]
class SetPluginConfiguration implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
    private readonly CKEditor5PluginManagerInterface $pluginManager,
    private readonly string $pluginId,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get(ConfigManagerInterface::class),
      $container->get(CKEditor5PluginManagerInterface::class),
      $plugin_id,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    $editor = $this->configManager->loadConfigEntityByName($configName);
    assert($editor instanceof EditorInterface);

    if ($editor->getEditor() !== 'ckeditor5') {
      throw new ConfigActionException(sprintf('The %s config action only works with editors that use CKEditor 5 and above.', $this->pluginId));
    }
    $editor_settings = $editor->getSettings();
    assert(is_array($value));
    if (!array_key_exists('id', $value)) {
      throw new ConfigActionException('The plugin_id parameter is required for this config action');
    }
    $plugin_id = $value['id'];
    if (!array_key_exists('configuration', $value)) {
      throw new ConfigActionException('The settings parameter is required for this config action');
    }
    /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginDefinition $definition */
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      if ($plugin_id == $id && $definition->isConfigurable()) {
        /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface $plugin */
        $plugin = $this->pluginManager->getPlugin($id, NULL);
        $plugin->setConfiguration($value['configuration']);
        $editor_settings['plugins'][$id] = $plugin->getConfiguration();
        // No need to examine any other plugins.
        break;
      }
    }

    $editor->setSettings($editor_settings)->save();
  }

}
