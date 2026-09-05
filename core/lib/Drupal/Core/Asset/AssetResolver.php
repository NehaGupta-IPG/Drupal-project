<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The default asset resolver.
 */
class AssetResolver implements AssetResolverInterface {

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * The library dependency resolver.
   *
   * @var \Drupal\Core\Asset\LibraryDependencyResolverInterface
   */
  protected $libraryDependencyResolver;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AssetResolver instance.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery service.
   * @param \Drupal\Core\Asset\LibraryDependencyResolverInterface $library_dependency_resolver
   *   The library dependency resolver.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger. Omitted for backwards compatibility; the 'asset' logger
   *   channel is used as a fallback.
   */
  public function __construct(LibraryDiscoveryInterface $library_discovery, LibraryDependencyResolverInterface $library_dependency_resolver, ModuleHandlerInterface $module_handler, ThemeManagerInterface $theme_manager, LanguageManagerInterface $language_manager, CacheBackendInterface $cache, ThemeHandlerInterface $theme_handler, ?LoggerInterface $logger = NULL) {
    $this->libraryDiscovery = $library_discovery;
    $this->libraryDependencyResolver = $library_dependency_resolver;
    $this->moduleHandler = $module_handler;
    $this->themeManager = $theme_manager;
    $this->languageManager = $language_manager;
    $this->cache = $cache;
    $this->themeHandler = $theme_handler;
    if (!isset($logger)) {
      $this->logger = \Drupal::logger('asset');
      @trigger_error('Constructing AssetResolver without a logger is deprecated in drupal:11.5.0 and the argument will be required in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3398525', E_USER_DEPRECATED);
    }
    else {
      $this->logger = $logger;
    }
  }

  /**
   * Returns the libraries that need to be loaded.
   *
   * For example, with core/a depending on core/c and core/b on core/d:
   * @code
   * $assets = new AttachedAssets();
   * $assets->setLibraries(['core/a', 'core/b', 'core/c']);
   * $assets->setAlreadyLoadedLibraries(['core/c']);
   * $resolver->getLibrariesToLoad($assets, 'js') === ['core/a', 'core/b', 'core/d']
   * @endcode
   *
   * The attached assets tend to be in the order that libraries were attached
   * during a request. To minimize the number of unique aggregated asset URLs
   * and files, we normalize the list by filtering out libraries that don't
   * include the asset type being built as well as ensuring a reliable order of
   * the libraries based on their dependencies.
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *   The assets attached to the current response.
   * @param string|null $asset_type
   *   The asset type to load.
   *
   * @return string[]
   *   A list of libraries and their dependencies, in the order they should be
   *   loaded, excluding any libraries that have already been loaded.
   */
  protected function getLibrariesToLoad(AttachedAssetsInterface $assets, ?string $asset_type = NULL) {
    // @see Drupal\FunctionalTests\Core\Asset\AssetOptimizationTestUmami
    // @todo https://www.drupal.org/project/drupal/issues/1945262
    $libraries_to_load = array_diff(
      $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getLibraries()),
      $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries())
    );
    if ($asset_type) {
      $libraries_to_load = $this->filterLibrariesByType($libraries_to_load, $asset_type);
    }

    // We now have a complete list of libraries requested. However, this list
    // could be in any order depending on when libraries were attached during
    // the page request, which can result in different file contents and URLs
    // even for an otherwise identical set of libraries. To ensure that any
    // particular set of libraries results in the same aggregate URL, sort the
    // libraries, then generate the minimum representative set again.
    sort($libraries_to_load);
    $minimum_libraries = $this->libraryDependencyResolver->getMinimalRepresentativeSubset($libraries_to_load);
    $libraries_to_load = array_diff(
      $this->libraryDependencyResolver->getLibrariesWithDependencies($minimum_libraries),
      $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries())
    );

    // Now remove any libraries without the relevant asset type again, since
    // they have been brought back in via dependencies.
    if ($asset_type) {
      $libraries_to_load = $this->filterLibrariesByType($libraries_to_load, $asset_type);
    }

    return $libraries_to_load;
  }

  /**
   * Filter libraries that don't contain an asset type.
   *
   * @param array $libraries
   *   An array of library definitions.
   * @param string $asset_type
   *   The type of asset, either 'js' or 'css'.
   *
   * @return array
   *   The filtered libraries array.
   */
  protected function filterLibrariesByType(array $libraries, string $asset_type): array {
    foreach ($libraries as $key => $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (empty($definition[$asset_type])) {
        unset($libraries[$key]);
      }
    }
    return $libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function getFontAssets(AttachedAssetsInterface $assets, ?LanguageInterface $language = NULL): array {
    if (!$assets->getLibraries()) {
      return [];
    }
    // Get the complete list of libraries to load including dependencies.
    $libraries_to_load = $this->getLibrariesToLoad($assets, 'fonts');

    if (!$libraries_to_load) {
      return [];
    }
    if (!isset($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }
    // Add the active theme name to the cache key since active themes may
    // implement hook_library_info_alter().
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $cid = 'fonts:' . $active_theme . ':' . $language->getId() . Crypt::hashBase64(serialize($libraries_to_load));
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }
    $fonts = [];
    foreach ($libraries_to_load as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      foreach ($definition['fonts'] as $font) {
        $fonts[] = $font;
      }
    }
    $this->cache->set($cid, $fonts, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $fonts;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportMap(): array {
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    // The import map is built from all known libraries: core, the enabled
    // modules, and the active theme including its base themes. Module scripts
    // attached later (e.g., in an Ajax response) may import any of these,
    // and browsers without support for multiple import maps cannot update
    // the map after the initial page load.
    $extensions = ['core'];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      $extensions[] = $module;
    }
    foreach ($this->getThemeWithBaseThemes($active_theme) as $theme) {
      $extensions[] = $theme;
    }
    $cid = 'importmap:' . $active_theme . ':' . hash('sha256', implode(',', $extensions));
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }
    $import_map = [
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ];
    $provenance = [
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ];
    $conflicts = [
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ];
    foreach ($extensions as $extension) {
      foreach ($this->libraryDiscovery->getLibrariesByExtension($extension) as $name => $definition) {
        if (empty($definition['importmap'])) {
          continue;
        }
        $this->mergeImportMap($import_map, $definition['importmap'], "$extension/$name", $provenance, $conflicts);
      }
    }
    // Allow modules and themes to alter the aggregated import map, e.g. to
    // resolve a conflict between libraries that define the same module
    // specifier with different values. The altered map is cached, tagged with
    // 'library_info', so the hook is only invoked when the library information
    // changes; implementations must therefore be static, like
    // hook_library_info_alter(). A conflict that is not resolved (by unsetting
    // it from $conflicts) is logged as an error.
    $this->moduleHandler->alter('importmap', $import_map, $conflicts);
    $this->themeManager->alter('importmap', $import_map, $conflicts);
    // The provenance is only used to log conflicts while the map is built;
    // it is not part of the cached import map. The import map cache is tagged
    // with 'library_info', so a conflict is logged again whenever the library
    // information changes.
    $this->logImportMapConflicts($conflicts, $provenance);
    $this->cache->set($cid, $import_map, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $import_map;
  }

  /**
   * Merges the import map of a single library into an aggregated import map.
   *
   * The first definition of a module specifier or integrity key wins. A
   * conflicting definition (the same key with a different value) is recorded
   * in $conflicts, so that it can be resolved in hook_importmap_alter().
   * Conflicts that are not resolved are logged as errors, since they will
   * most likely result in broken JavaScript.
   *
   * @param array $import_map
   *   The aggregated import map, by reference.
   * @param array $new_import_map
   *   The import map of the library being merged in.
   * @param string $library
   *   The name of the library being merged in, e.g. 'module/library'.
   * @param array $provenance
   *   By reference. The aggregated provenance, keyed by section and key, with
   *   the name of the library that provided the entry.
   * @param array $conflicts
   *   By reference. The conflicts found while merging, with the same
   *   structure as passed to hook_importmap_alter().
   */
  protected function mergeImportMap(array &$import_map, array $new_import_map, string $library, array &$provenance, array &$conflicts): void {
    foreach ($new_import_map['imports'] as $key => $value) {
      $this->mergeImportMapEntry($import_map['imports'], $provenance['imports'], $conflicts['imports'], (string) $key, $value, $library);
    }
    foreach ($new_import_map['scopes'] as $scope => $entries) {
      if (!isset($import_map['scopes'][$scope])) {
        $import_map['scopes'][$scope] = [];
        $provenance['scopes'][$scope] = [];
        $conflicts['scopes'][$scope] = [];
      }
      foreach ($entries as $key => $value) {
        $this->mergeImportMapEntry($import_map['scopes'][$scope], $provenance['scopes'][$scope], $conflicts['scopes'][$scope], (string) $key, $value, $library);
      }
    }
    foreach ($new_import_map['integrity'] as $key => $value) {
      $this->mergeImportMapEntry($import_map['integrity'], $provenance['integrity'], $conflicts['integrity'], (string) $key, $value, $library);
    }
  }

  /**
   * Merges a single import map entry into an aggregated set of entries.
   *
   * @param array $entries
   *   The aggregated entries, by reference, keyed by the module specifier or
   *   path.
   * @param array $provenance
   *   By reference. The aggregated provenance, keyed like $entries, with the
   *   name of the library that provided the entry.
   * @param array $conflicts
   *   By reference. The conflicts found while merging, keyed by the module
   *   specifier or path, for the section the entry belongs to. Each conflict
   *   lists the definitions involved, keyed by the name of the library that
   *   provided the definition.
   * @param string $key
   *   The module specifier or path.
   * @param mixed $value
   *   The entry value.
   * @param string $library
   *   The name of the library providing the entry, e.g. 'module/library'.
   */
  protected function mergeImportMapEntry(array &$entries, array &$provenance, array &$conflicts, string $key, mixed $value, string $library): void {
    if (array_key_exists($key, $entries)) {
      // Compare the entries without their version. Two libraries may map the
      // same module specifier to the same file, with different library
      // versions. The version only affects the query string of the module
      // URL, not the module itself, so it is not a conflict.
      $existing = $entries[$key];
      $existing_without_version = $existing;
      $value_without_version = $value;
      unset($existing_without_version['version'], $value_without_version['version']);
      if ($existing_without_version === $value_without_version) {
        // The entry is identical to the existing one: nothing to do.
        return;
      }
      // A conflicting definition: the entry of the library that defined the
      // key first is kept. Record the definitions involved in the conflict,
      // so that it can be resolved in hook_importmap_alter().
      $existing_library = $provenance[$key];
      if (!isset($conflicts[$key])) {
        $conflicts[$key] = [$existing_library => $existing];
      }
      $conflicts[$key][$library] = $value;
      return;
    }
    $entries[$key] = $value;
    $provenance[$key] = $library;
  }

  /**
   * Logs the import map conflicts not resolved in hook_importmap_alter().
   *
   * A conflict is a module specifier or integrity key that is defined by
   * multiple libraries with different values. The merge resolves the conflict
   * in favor of the first definition in the merge order, so the other
   * definitions are dropped, and will most likely result in broken
   * JavaScript.
   *
   * @param array $conflicts
   *   The conflicts remaining after hook_importmap_alter(), with the same
   *   structure as passed to the hook.
   * @param array $provenance
   *   The provenance of the merged import map, keyed by section and key, with
   *   the name of the library that provided the entry that was used.
   */
  protected function logImportMapConflicts(array $conflicts, array $provenance): void {
    foreach ($conflicts['imports'] as $key => $definitions) {
      $this->logImportMapConflict($key, "'imports' section", $definitions, $provenance['imports'][$key]);
    }
    foreach ($conflicts['scopes'] as $scope => $conflicts_by_key) {
      foreach ($conflicts_by_key as $key => $definitions) {
        $this->logImportMapConflict($key, sprintf("'scopes' section, scope '%s'", $scope), $definitions, $provenance['scopes'][$scope][$key]);
      }
    }
    foreach ($conflicts['integrity'] as $key => $definitions) {
      $this->logImportMapConflict($key, "'integrity' section", $definitions, $provenance['integrity'][$key]);
    }
  }

  /**
   * Logs an import map conflict for a module specifier or integrity key.
   *
   * The conflict is logged for each definition that was not used by the
   * merge.
   *
   * @param string $key
   *   The module specifier or path.
   * @param string $context
   *   A description of the section the entry belongs to, for the log message.
   * @param array $definitions
   *   The definitions involved in the conflict, keyed by the name of the
   *   library that provided the definition.
   * @param string $winner
   *   The name of the library whose definition was used by the merge, e.g.
   *   'module/library'.
   */
  protected function logImportMapConflict(string $key, string $context, array $definitions, string $winner): void {
    foreach ($definitions as $library => $value) {
      if ($library === $winner) {
        continue;
      }
      $this->logger->error(sprintf("Import map conflict: the module specifier '%s' in the %s is defined by both %s and %s with different values. The value from %s is used. Remove the duplicate definition from one of the libraries, or resolve the conflict in hook_importmap_alter().", $key, $context, $winner, $library, $winner));
    }
  }

  /**
   * Returns the given theme and its base themes.
   *
   * The base themes come first, starting with the most distant ancestor,
   * followed by the given theme itself.
   *
   * @param string $theme
   *   The machine name of a theme.
   *
   * @return string[]
   *   The machine names of the base themes and the theme itself.
   */
  protected function getThemeWithBaseThemes(string $theme): array {
    $themes = [];
    $name = $theme;
    while (TRUE) {
      $themes[] = $name;
      try {
        $extension = $this->themeHandler->getTheme($name);
      }
      catch (UnknownExtensionException) {
        break;
      }
      $name = $extension->info['base theme'] ?? '';
      if (!is_string($name) || $name === '') {
        break;
      }
    }
    return array_reverse($themes);
  }

  /**
   * {@inheritdoc}
   */
  public function getModulePreloads(AttachedAssetsInterface $assets): array {
    if (!$assets->getLibraries()) {
      return [];
    }
    // Get the complete list of libraries to load, including dependencies.
    $libraries_to_load = $this->getLibrariesToLoad($assets);

    if (!$libraries_to_load) {
      return [];
    }
    // Add the active theme name to the cache key, since active themes may
    // implement hook_library_info_alter().
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $cid = 'importmap_preloads:' . $active_theme . ':' . Crypt::hashBase64(serialize($libraries_to_load));
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }
    $preloads = [];
    foreach ($libraries_to_load as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (empty($definition['importmap'])) {
        continue;
      }
      foreach ($definition['importmap']['imports'] as $entry) {
        // Module specifier mappings with a trailing slash map a class of
        // modules to a class of URLs, and there is no single module to
        // preload.
        if (str_ends_with($entry['data'], '/')) {
          continue;
        }
        $preloads[$entry['data']] = $entry;
      }
      foreach ($definition['importmap']['scopes'] as $entries) {
        foreach ($entries as $entry) {
          if (str_ends_with($entry['data'], '/')) {
            continue;
          }
          $preloads[$entry['data']] = $entry;
        }
      }
    }
    $preloads = array_values($preloads);
    $this->cache->set($cid, $preloads, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $preloads;
  }

  /**
   * {@inheritdoc}
   */
  public function getCssAssets(AttachedAssetsInterface $assets, $optimize, ?LanguageInterface $language = NULL, bool $with_dependencies = TRUE) {
    if (!$assets->getLibraries()) {
      return [];
    }
    if ($with_dependencies) {
      // Get the complete list of libraries to load including dependencies.
      $libraries_to_load = $this->getLibrariesToLoad($assets, 'css');
    }
    else {
      $libraries_to_load = $assets->getLibraries();
    }

    if (!$libraries_to_load) {
      return [];
    }
    if (!isset($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }
    // Add the active theme name to the cache key since active themes may
    // implement hook_library_info_alter().
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    // Add the default theme name to the cache key since css generated for an
    // active admin theme may include the default theme's ckeditor5-stylesheets
    // and default themes may be set conditionally and dynamically.
    $default_theme = $this->themeHandler->getDefault();
    $cid = 'css:' . $active_theme . ':' . $default_theme . ':' . $language->getId() . Crypt::hashBase64(serialize($libraries_to_load)) . (int) $optimize;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $css = [];
    $default_options = [
      'type' => 'file',
      'group' => CSS_AGGREGATE_DEFAULT,
      'weight' => 0,
      'media' => 'all',
      'preprocess' => TRUE,
    ];

    foreach ($libraries_to_load as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      foreach ($definition['css'] as $options) {
        // Libraries are loaded based on dependencies, then their attaching
        // order. Individual asset files are ordered how they are defined within
        // the library itself.
        // Re-adding an asset file will rewrite the resulting array leading to
        // an incorrect order of asset files, i.e. the last occurrence of the
        // particular asset will win, but the desired behavior is the opposite -
        // the first occurrence must win.
        // So, let's skip processing of already added asset files.
        if (array_key_exists($options['data'], $css)) {
          continue;
        }
        $options += $default_options;
        // Copy the asset library license information to each file.
        $options['license'] = $definition['license'];

        // Files with a query string cannot be preprocessed.
        if ($options['type'] === 'file' && $options['preprocess'] && str_contains($options['data'], '?')) {
          $options['preprocess'] = FALSE;
        }
        $options['library'] = $library;
        $options['aggregate_target'] = $definition['aggregate_target'] ?? ['js' => FALSE, 'css' => FALSE];

        // CSS files are being keyed by the full path.
        $css[$options['data']] = $options;
      }
    }

    // Allow modules and themes to alter the CSS assets.
    $this->moduleHandler->alter('css', $css, $assets, $language);
    $this->themeManager->alter('css', $css, $assets, $language);

    if (!empty($css)) {
      // Sort CSS items, so that they appear in the correct order.
      uasort($css, [static::class, 'sort']);

      if ($optimize) {
        $css = \Drupal::service('asset.css.collection_optimizer')->optimize($css, array_values($libraries_to_load), $language);
      }
    }
    $this->cache->set($cid, $css, CacheBackendInterface::CACHE_PERMANENT, ['library_info']);

    return $css;
  }

  /**
   * Returns the JavaScript settings assets for this response's libraries.
   *
   * Gathers all drupalSettings from all libraries in the attached assets
   * collection and merges them.
   *
   * @param \Drupal\Core\Asset\AttachedAssetsInterface $assets
   *   The assets attached to the current response.
   *
   * @return array
   *   A (possibly optimized) collection of JavaScript assets.
   */
  protected function getJsSettingsAssets(AttachedAssetsInterface $assets) {
    $settings = [];

    foreach ($this->getLibrariesToLoad($assets, 'js') as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (isset($definition['drupalSettings'])) {
        $settings = NestedArray::mergeDeepArray([$settings, $definition['drupalSettings']], TRUE);
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsAssets(AttachedAssetsInterface $assets, $optimize, ?LanguageInterface $language = NULL, bool $with_dependencies = TRUE) {
    $asset_settings = $assets->getSettings();
    if (!$assets->getLibraries() && !$asset_settings) {
      return [[], []];
    }
    if (!isset($language)) {
      $language = $this->languageManager->getCurrentLanguage();
    }
    $theme_info = $this->themeManager->getActiveTheme();

    if ($with_dependencies) {
      // Get the complete list of libraries to load including dependencies.
      $libraries_to_load = $this->getLibrariesToLoad($assets, 'js');
    }
    else {
      $libraries_to_load = $assets->getLibraries();
    }

    // Collect all libraries that contain JS assets and are in the header.
    $header_js_libraries = [];
    foreach ($libraries_to_load as $key => $library) {
      [$extension, $name] = explode('/', $library, 2);
      $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
      if (!empty($definition['header'])) {
        $header_js_libraries[] = $library;
      }
    }

    // If all the libraries to load contained only CSS, there is nothing further
    // to do here, so return early.
    if (!$libraries_to_load && !$asset_settings) {
      return [[], []];
    }

    // Add the theme name to the cache key since themes may implement
    // hook_library_info_alter(). Additionally add the current language to
    // support translation of JavaScript files via hook_js_alter().
    $cid = 'js:' . $theme_info->getName() . ':' . $language->getId() . ':' . Crypt::hashBase64(serialize($libraries_to_load)) . ':' . (int) $optimize;

    if ($cached = $this->cache->get($cid)) {
      [$js_assets_header, $js_assets_footer, $settings, $settings_in_header] = $cached->data;
    }
    else {
      $javascript = [];
      $default_options = [
        'type' => 'file',
        'group' => JS_DEFAULT,
        'weight' => 0,
        'cache' => TRUE,
        'preprocess' => TRUE,
        'attributes' => [],
        'version' => NULL,
      ];

      // The current list of header JS libraries are only those libraries that
      // are in the header, but their dependencies must also be loaded for them
      // to function correctly, so update the list with those.
      $header_js_libraries = $this->libraryDependencyResolver->getLibrariesWithDependencies($header_js_libraries);

      foreach ($libraries_to_load as $library) {
        [$extension, $name] = explode('/', $library, 2);
        $definition = $this->libraryDiscovery->getLibraryByName($extension, $name);
        foreach ($definition['js'] as $options) {
          // Libraries are loaded based on dependencies, then their attaching
          // order. Individual asset files are ordered how they are defined
          // within the library itself.
          // Re-adding an asset file will rewrite the resulting array leading to
          // an incorrect order of asset files, i.e. the last occurrence of the
          // particular asset will win, but the desired behavior is the opposite
          // - the first occurrence must win.
          // So, let's skip processing of already added asset files.
          if (array_key_exists($options['data'], $javascript)) {
            continue;
          }
          $options += $default_options;
          // Copy the asset library license information to each file.
          $options['license'] = $definition['license'];

          // 'scope' is a calculated option, based on which libraries are
          // marked to be loaded from the header (see above).
          $options['scope'] = in_array($library, $header_js_libraries) ? 'header' : 'footer';

          // Preprocess can only be set if caching is enabled and no
          // attributes are set.
          $options['preprocess'] = $options['cache'] && empty($options['attributes']) ? $options['preprocess'] : FALSE;

          $options['library'] = $library;
          $options['aggregate_target'] = $definition['aggregate_target'] ?? ['js' => FALSE, 'css' => FALSE];
          // Local and external files must keep their name as the associative
          // key so the same JavaScript file is not added twice.
          $javascript[$options['data']] = $options;
        }
      }

      // Allow modules and themes to alter the JavaScript assets.
      $this->moduleHandler->alter('js', $javascript, $assets, $language);
      $this->themeManager->alter('js', $javascript, $assets, $language);

      // Sort JavaScript assets, so that they appear in the correct order.
      uasort($javascript, [static::class, 'sort']);

      // Prepare the return value: filter JavaScript assets per scope.
      $js_assets_header = [];
      $js_assets_footer = [];
      foreach ($javascript as $key => $item) {
        if ($item['scope'] == 'header') {
          $js_assets_header[$key] = $item;
        }
        elseif ($item['scope'] == 'footer') {
          $js_assets_footer[$key] = $item;
        }
      }

      if ($optimize) {
        $collection_optimizer = \Drupal::service('asset.js.collection_optimizer');
        $js_assets_header = $collection_optimizer->optimize($js_assets_header, $libraries_to_load);
        $js_assets_footer = $collection_optimizer->optimize($js_assets_footer, $libraries_to_load);
      }

      // Always build settings from js libraries. They may or may not be
      // used later depending on whether the core/drupalSettings library is
      // requested.
      $settings = $this->getJsSettingsAssets($assets);
      // Allow modules to add cached JavaScript settings.
      $this->moduleHandler->invokeAllWith('js_settings_build', function (callable $hook, string $module) use (&$settings, $assets) {
        $hook($settings, $assets);
      });
      $settings_in_header = in_array('core/drupalSettings', $header_js_libraries);
      $this->cache->set($cid, [$js_assets_header, $js_assets_footer, $settings, $settings_in_header], CacheBackendInterface::CACHE_PERMANENT, ['library_info']);
    }

    // If the core/drupalSettings library is being loaded or is already
    // loaded, get the JavaScript settings assets, and convert them into a
    // single "regular" JavaScript asset. But only if there are settings to
    // add. Do the quickest checks first.
    $process_settings = FALSE;
    if (count($libraries_to_load) > 0 || count($asset_settings) > 0) {
      $process_settings = in_array('core/drupalSettings', $libraries_to_load) || in_array('core/drupalSettings', $this->libraryDependencyResolver->getLibrariesWithDependencies($assets->getAlreadyLoadedLibraries()));
    }
    if ($process_settings) {
      // Attached settings override both library definitions and
      // hook_js_settings_build().
      $settings = NestedArray::mergeDeepArray([$settings, $asset_settings], TRUE);
      // Allow modules and themes to alter the JavaScript settings.
      $this->moduleHandler->alter('js_settings', $settings, $assets);
      $this->themeManager->alter('js_settings', $settings, $assets);
      // Update the $assets object accordingly, so that it reflects the final
      // settings.
      $assets->setSettings($settings);
      // Convert ajaxPageState to a compressed string from an array, since it is
      // used by ajax.js to pass to AJAX requests as a query parameter.
      if (isset($settings['ajaxPageState']['libraries'])) {
        $settings['ajaxPageState']['libraries'] = UrlHelper::compressQueryParameter($settings['ajaxPageState']['libraries']);
      }
      $settings_as_inline_javascript = [
        'type' => 'setting',
        'group' => JS_SETTING,
        'data' => $settings,
      ];
      $settings_js_asset = ['drupalSettings' => $settings_as_inline_javascript];
      // Prepend to the list of JS assets, to render it first. Preferably in
      // the footer, but in the header if necessary.
      if ($settings_in_header) {
        $js_assets_header = $settings_js_asset + $js_assets_header;
      }
      else {
        $js_assets_footer = $settings_js_asset + $js_assets_footer;
      }
    }
    return [
      $js_assets_header,
      $js_assets_footer,
    ];
  }

  /**
   * Sorts CSS and JavaScript resources.
   *
   * Callback for uasort().
   *
   * This sort order helps optimize front-end performance while providing
   * modules and themes with the necessary control for ordering the CSS and
   * JavaScript appearing on a page.
   *
   * @param array $a
   *   First item for comparison. The compared items should be associative
   *   arrays of member items.
   * @param array $b
   *   Second item for comparison.
   *
   * @return int
   *   The comparison result for uasort().
   */
  public static function sort(array $a, array $b) {
    // First order by group, so that all items in the CSS_AGGREGATE_DEFAULT
    // group appear before items in the CSS_AGGREGATE_THEME group. Modules may
    // create additional groups by defining their own constants.
    if ($a['group'] < $b['group']) {
      return -1;
    }
    elseif ($a['group'] > $b['group']) {
      return 1;
    }
    elseif ($a['weight'] < $b['weight']) {
      return -1;
    }
    elseif ($a['weight'] > $b['weight']) {
      return 1;
    }
    else {
      return 0;
    }
  }

}
