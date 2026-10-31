<?php

namespace Drupal\Core\Theme;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Extension\ThemeHandlerInterface;

/**
 * Determines which component should be used.
 */
class ComponentNegotiator {

  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = [
    'moduleExtensionList' => 'extension.list.module',
    'themeManager' => 'theme.manager',
  ];

  /**
   * Holds the component IDs from previous negotiations.
   *
   * @var array
   */
  protected array $cache = [];

  /**
   * The theme handler.
   */
  protected ThemeHandlerInterface $themeHandler;

  public function __construct(
    ThemeHandlerInterface|ThemeManagerInterface $themeHandler,
  ) {
    if ($themeHandler instanceof ThemeManagerInterface) {
      @trigger_error('Passing the theme.manager service to ' . __METHOD__ . '() is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. Pass the theme_handler service instead. See https://www.drupal.org/node/3549491', E_USER_DEPRECATED);
      $themeHandler = \Drupal::service('theme_handler');
    }
    $this->themeHandler = $themeHandler;
  }

  /**
   * Negotiates the active component for the current request.
   *
   * @param string $component_id
   *   The requested component id. Ex: 'my-button', 'my-button--primary',
   *   'component_example:my-button--primary', ...
   * @param array[] $all_definitions
   *   All the plugin definitions for components keyed by plugin ID.
   *
   * @return string|null
   *   The negotiated plugin ID or null if no negotiation was successful.
   */
  public function negotiate(string $component_id, array $all_definitions): ?string {
    $cache_key = $this->generateCacheKey($component_id);
    if (array_key_exists($cache_key, $this->cache)) {
      return $this->cache[$cache_key];
    }
    $negotiated = $this->doNegotiate($component_id, $all_definitions);
    $this->cache[$cache_key] = $negotiated;
    return $negotiated;
  }

  /**
   * Negotiates the active component for the current request.
   *
   * @param string $component_id
   *   The requested component id. Ex: 'my-button', 'my-button--primary',
   *   'component_example:my-button--primary', ...
   * @param array[] $all_definitions
   *   All the plugin definitions for components keyed by plugin ID.
   *
   * @return string|null
   *   The negotiated plugin ID or null if no negotiation was successful.
   */
  private function doNegotiate(string $component_id, array $all_definitions): ?string {
    // Consider only the component definitions matching the component ID in the
    // 'replaces' key.
    $matches = array_filter(
      $all_definitions,
      static fn(array $definition) => $component_id === ($definition['replaces'] ?? NULL),
    );
    $negotiated_plugin_id = $this->maybeNegotiateByTheme($matches);
    if ($negotiated_plugin_id) {
      return $negotiated_plugin_id;
    }
    return $this->maybeNegotiateByModule($matches);
  }

  /**
   * See if there is a candidate in the theme hierarchy.
   *
   * @param array[] $candidates
   *   All the components that might be a match.
   *
   * @return string|null
   *   The plugin ID for the negotiated component, or NULL if none was found.
   */
  private function maybeNegotiateByTheme(array $candidates): ?string {
    $theme_name = $this->themeHandler->getDefault();
    if (!$theme_name || !$this->themeHandler->themeExists($theme_name)) {
      return NULL;
    }
    // Let's do theme based negotiation. The base themes are ordered from the
    // root base theme to the direct parent, so reverse them to give priority
    // to the closest ancestor. The property is only set for sub-themes.
    $base_theme_names = array_reverse(array_keys($this->themeHandler->getTheme($theme_name)->base_themes ?? []));
    $considered_themes = [$theme_name, ...$base_theme_names];
    // Only consider components in the theme hierarchy tree.
    $candidates = array_filter(
      $candidates,
      static fn(array $definition) => $definition['extension_type'] === ExtensionType::Theme
        && in_array($definition['provider'], $considered_themes, TRUE)
    );
    if (empty($candidates)) {
      return NULL;
    }
    $theme_weights = array_flip($considered_themes);
    $sort_by_theme_weight = static fn(array $definition_a, array $definition_b) =>
      $theme_weights[$definition_a['provider']] <=> $theme_weights[$definition_b['provider']];
    // Sort the candidates by weight and choose the one with the lowest weight.
    uasort($candidates, $sort_by_theme_weight);
    $definition = reset($candidates);
    return $definition['id'] ?? NULL;
  }

  /**
   * Negotiate the component from the list of candidates for a module.
   *
   * @param array[] $candidates
   *   The candidate definitions.
   *
   * @return string|null
   *   The negotiated plugin ID, or NULL if none found.
   */
  private function maybeNegotiateByModule(array $candidates): ?string {
    foreach ($candidates as $candidate) {
      if ($candidate['extension_type'] === ExtensionType::Module) {
        return $candidate['id'];
      }
    }
    return NULL;
  }

  /**
   * Generates the cache key for the current theme and the provided component.
   *
   * @param string $component_id
   *   The component ID.
   *
   * @return string
   *   The cache key.
   */
  private function generateCacheKey(string $component_id): string {
    return sprintf(
      'component-negotiation::%s::%s',
      $component_id,
      $this->themeHandler->getDefault()
    );
  }

  /**
   * Clears the negotiation cache.
   */
  public function clearCache(): void {
    $this->cache = [];
  }

}
