<?php

namespace Drupal\language;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overrides the language_manager service to point to language's module one.
 */
class LanguageServiceAlter extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $definition = $container->getDefinition('language_manager');
    $definition->setClass('Drupal\language\ConfigurableLanguageManager')
      ->addArgument(new Reference('config.factory'))
      ->addArgument(new Reference('module_handler'))
      ->addArgument(new Reference('language.config_factory_override'))
      ->addArgument(new Reference('request_stack'))
      ->addArgument(new Reference('cache.bootstrap'));
    if ($default_language_values = $this->getDefaultLanguageValues()) {
      $container->setParameter('language.default_values', $default_language_values);
    }
  }

  /**
   * Gets the default language values.
   *
   * @return array|bool
   *   Returns the default language values for the language configured in
   *   system.site:default_langcode if the corresponding configuration entity
   *   exists, otherwise FALSE.
   */
  protected function getDefaultLanguageValues(): array|FALSE {
    $config_storage = BootstrapConfigStorageFactory::get();
    $system = $config_storage->read('system.site');
    // In Kernel tests it's possible this code is called before system.site
    // exists. In such cases behave as though the corresponding language
    // configuration entity does not exist.
    if ($system === FALSE) {
      return FALSE;
    }
    $default_language = $config_storage->read('language.entity.' . $system['default_langcode']);
    if (is_array($default_language)) {
      return $default_language;
    }
    return FALSE;
  }

}
