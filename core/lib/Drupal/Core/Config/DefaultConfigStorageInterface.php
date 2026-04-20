<?php

namespace Drupal\Core\Config;

/**
 * Provides access to default configuration for locale integration.
 *
 * Allows unified access to default configuration from multiple sources:
 * - Required default configuration (config/install/*)
 * - Optional default configuration (config/optional/*)
 * - Configuration provided by Recipes (config/recipes/*)
 *
 * These sources are considered equal in terms of how locale module interacts
 * with them for translation. Their translatable source strings are exposed
 * for interface translation and participate in remote translation updates.
 */
interface DefaultConfigStorageInterface {

  /**
   * Read a configuration from install storage or default languages.
   *
   * @param string $name
   *   Configuration object name.
   *
   * @return array
   *   Configuration data from install storage or default language.
   */
  public function read($name);
  
  /**
   * Return the list of configuration in install storage and current languages.
   *
   * @return array
   *   List of configuration in install storage and current languages.
   */
  public function listAll();

  /**
   * Get all configuration names and folders for a list of modules or themes.
   *
   * @param string $type
   *   Type of components: 'module' | 'theme' | 'profile'.
   * @param array $list
   *   Array of theme or module names.
   *
   * @return array
   *   Configuration names provided by that component. In case of language
   *   module this list is extended with configured languages that have
   *   predefined names as well.
   */
  public function getComponentNames($type, array $list) ;

}
