<?php

namespace Drupal\migrate\Plugin;

use Drupal\Component\Plugin\Attribute\AttributeInterface;
use Drupal\Component\Plugin\Attribute\PluginID;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages migrate plugins.
 *
 * @see hook_migrate_info_alter()
 * @see \Drupal\migrate\Attribute\MigrateSource
 * @see \Drupal\migrate\Plugin\MigrateSourceInterface
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 * @see \Drupal\migrate\Attribute\MigrateProcess
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 * @see \Drupal\migrate\Plugin\migrate\process\ProcessPluginBase
 * @see plugin_api
 *
 * @ingroup migration
 */
class MigratePluginManager extends DefaultPluginManager implements MigratePluginManagerInterface {

  public function __construct($type, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, $attribute = PluginID::class, $annotation = 'Drupal\Component\Annotation\PluginID') {
    if (!is_subclass_of($attribute, AttributeInterface::class)) {
      // Backward compatibility.
      $annotation = $attribute;
      $attribute = PluginID::class;
    }
    parent::__construct("Plugin/migrate/$type", $namespaces, $module_handler, NULL, $attribute, $annotation);
    $this->alterInfo('migrate_' . $type . '_info');
    $this->setCacheBackend($cache_backend, 'migrate_plugins_' . $type);
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = [], ?MigrationInterface $migration = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $migration);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $migration);
    }
    return $plugin;
  }

}
