<?php

namespace Drupal\locale;

use Drupal\Core\Config\StorageInterface;

/**
 * Provides a wrapper around existing default config storage.
 *
 * Adds a recipe config storage as a configuration source.
 *
 * This allows locale system to read configuration from the recipe when
 * determining translatability.
 *
 * @see \Drupal\locale\LocaleDefaultConfigStorage
 * @see \Drupal\locale\LocaleConfigManager::updateConfigTranslationsFromStorage()
 */
class RecipeDefaultConfigStorage implements DefaultConfigStorageInterface {

  /**
   * The storage instance for reading configuration data.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $recipeStorage;

  /**
   * The fallback default config storage.
   *
   * @var \Drupal\locale\DefaultConfigStorageInterface
   */
  protected $defaultConfigStorage;

  /**
   * Constructs a new RecipeDefaultConfigStorage object.
   *
   * @param \Drupal\Core\Config\StorageInterface $recipe_storage
   *   The recipe's configuration storage.
   * @param \Drupal\locale\DefaultConfigStorageInterface $default_config_storage
   *   The fallback default config storage.
   */
  public function __construct(StorageInterface $recipe_storage, DefaultConfigStorageInterface $default_config_storage) {
    $this->recipeStorage = $recipe_storage;
    $this->defaultConfigStorage = $default_config_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    if ($this->recipeStorage->exists($name)) {
      return $this->recipeStorage->read($name);
    }
    else {
      return $this->defaultConfigStorage->read($name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function listAll() {
    return array_unique(
      array_merge(
        $this->defaultConfigStorage->listAll(),
        $this->recipeStorage->listAll()
      )
    );
  }

  /**
   * Adds recipe as a component type for configuration names.
   *
   * @param string $type
   *   Type of components: 'module' | 'theme' | 'profile' | 'recipe'.
   * @param array $list
   *   Array of theme or module names.
   *
   * @return array
   *   Configuration names provided by that component.
   */
  public function getComponentNames($type, array $list) {
    if ($type === 'recipe') {
      $names = [];
      /** @var \Drupal\Core\Recipe\Recipe $recipe */
      foreach ($list as $recipe) {
        $recipe_names = $recipe->config->getConfigStorage()->listAll();
        $names = array_merge($names, $recipe_names);
      }
      return $names;
    }
    else {
      return $this->defaultConfigStorage->getComponentNames($type, $list);
    }
  }

}
