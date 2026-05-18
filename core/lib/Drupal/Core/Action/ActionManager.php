<?php

namespace Drupal\Core\Action;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\CategorizingPluginManagerTrait;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an Action plugin manager.
 *
 * @see \Drupal\Core\Annotation\Action
 * @see \Drupal\Core\Action\ActionInterface
 * @see \Drupal\Core\Action\ActionBase
 * @see plugin_api
 */
class ActionManager extends DefaultPluginManager implements CategorizingPluginManagerInterface {

  use CategorizingPluginManagerTrait;

  /**
   * Constructs a new class instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Language\LanguageManagerInterface|null $languageManager
   *   The language manager. Required for derived action plugins whose
   *   labels resolve from translatable entity data to honor config language
   *   overrides per request. Passing NULL is deprecated and falls back to
   *   the legacy single-cache behavior with potentially stale labels.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected ?LanguageManagerInterface $languageManager = NULL,
  ) {
    parent::__construct('Plugin/Action', $namespaces, $module_handler, ActionInterface::class, Action::class, 'Drupal\Core\Annotation\Action');
    $this->alterInfo('action_info');
    $cache_key = 'action_info';
    if ($this->languageManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $languageManager argument is deprecated in drupal:11.4.0 and will be required in drupal:12.0.0. See https://www.drupal.org/node/3589007', E_USER_DEPRECATED);
    }
    else {
      $cache_key .= ':' . $this->languageManager->getCurrentLanguage()->getId();
    }
    $this->setCacheBackend($cache_backend, $cache_key);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    if ($this->languageManager === NULL) {
      parent::clearCachedDefinitions();
      return;
    }
    $cids = [];
    foreach ($this->languageManager->getLanguages() as $language) {
      $cids[] = 'action_info:' . $language->getId();
    }
    $this->cacheBackend->deleteMultiple($cids);
    $this->definitions = NULL;
  }

  /**
   * Gets the plugin definitions for this entity type.
   *
   * @param string $type
   *   The entity type name.
   *
   * @return array
   *   An array of plugin definitions for this entity type.
   */
  public function getDefinitionsByType($type) {
    return array_filter($this->getDefinitions(), function ($definition) use ($type) {
      return $definition['type'] === $type;
    });
  }

}
