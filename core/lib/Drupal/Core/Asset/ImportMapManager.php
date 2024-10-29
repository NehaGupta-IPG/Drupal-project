<?php

declare(strict_types=1);

namespace Drupal\Core\Asset;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\FileCache\FileCacheInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Defines a plugin manager for import maps.
 *
 * To declare an import map, add a file named MODULE_NAME.importmap.yml to the
 * root of your module. The file should contain a top level key 'imports'. Each
 * entry under 'imports' represents an entry in the import map.
 *
 * Example:
 * @code
 * imports:
 *   my-library:
 *     path: js/dist/my-library.js
 *   another-library:
 *     path: js/dist/another-library.js
 * @endcode
 * JavaScript code that wishes to consume your imports in an ES module.
 *
 * @code
 * // Note the naked import here, no path - simply 'my-library'.
 * import myLibrary from 'my-library';
 * @endcode
 *
 * To make use of a bundler to build the consuming code, you need to configure
 * the build step to mark these imports as external. For example with Vite.
 * @code
 * const viteConfig = {
 *   // ...
 *   build: {
 *     rollupOptions: {
 *       external: ["my-library", "another-library"],
 *   },
 *   // ...
 * }
 * @endcode
 * This will make sure that the naked import is retained in the built code.
 * Similar options exist for other front-end bundlers.
 *
 * Consuming code can make use of the attributes key in libraries.yml to set
 * type="module" on the script tag.
 * Example:
 * @code
 *   cards:
 *    css:
 *     component:
 *       css/card.css: {}
 *    js:
 *      // This script will be rendered as <script type="module"> and hence be
 *     // treated as an ES module.
 *     js/dist/card.js: { minified: true, attributes: { type: module } }}
 * @endcode
 *
 * To load two different versions of a module in an import map add a top-level
 * 'scopes' key to the MODULE_NAME.importmap.yml file.
 * Example:
 * @code
 *  imports:
 *    my-library:
 *      path: js/dist/my-library-v2.js
 *  scopes:
 *    js/v1:
 *      my-library:
 *       path: js/dist/my-library-v1.js
 * @endcode
 * In this example, consuming code from the directory "js/v1" (relative to the
 * module declaring the importmap.yml file) will resolve my-library" to
 * "js/dist/my-library-v1.js". All other consuming code that imports from
 * "my-library" will resolve to "js/dist/my-library-v2.js".
 *
 * The MODULE_NAME.importmap.yml file must contain at least one of the 'scopes'
 * and 'imports' top-level keys.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script/type/importmap
 * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules
 * @see https://rollupjs.org/configuration-options/#external
 * @see https://webpack.js.org/configuration/externals/#externals
 */
final class ImportMapManager extends CacheCollector implements ImportMapManagerInterface {

  protected const MODULE_IMPORT_MAPS = '__modules';

  /**
   * File cache.
   *
   * @var \Drupal\Component\FileCache\FileCacheInterface
   */
  protected readonly FileCacheInterface $fileCache;

  /**
   * Constructs an ImportMapManager object.
   */
  public function __construct(
    #[Autowire('@cache.discovery')]
    CacheBackendInterface $cache,
    #[Autowire('@lock')]
    LockBackendInterface $lock,
    #[Autowire('%app.root%')]
    protected readonly string $rootPath,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ThemeHandlerInterface $themeHandler,
    protected readonly ExtensionPathResolver $extensionPathResolver,
    protected readonly ThemeExtensionList $themeExtensionList,
    #[Autowire('@logger.channel.default')]
    protected readonly LoggerChannelInterface $loggerChannel,
    protected readonly FileUrlGeneratorInterface $fileUrlGenerator,
    protected readonly ThemeManagerInterface $themeManager,
  ) {
    parent::__construct('importmap', $cache, $lock, ['importmap']);
    $this->fileCache = FileCacheFactory::get('importmap');
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveCacheMiss($key): array {
    $this->storage[$key] = $this->buildImportMap($key);
    $this->persist($key);

    return $this->storage[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function getImportMapForTheme(string $theme): array {
    // The returned map combines import maps for the installed modules and those
    // from the given theme (and any of its base themes).
    return NestedArray::mergeDeep($this->get(self::MODULE_IMPORT_MAPS), $this->get($theme));
  }

  /**
   * Build import maps for the given key.
   *
   * @param string $key
   *   Either self::MODULE_IMPORT_MAPS or a theme machine name.
   *
   * @return array
   *   Built import maps, with any paths resolved.
   */
  protected function buildImportMap(string $key): array {
    $import_maps = [];
    if ($key === self::MODULE_IMPORT_MAPS) {
      // We need to build for modules.
      foreach ($this->moduleHandler->getModuleList() as $extension => $info) {
        $import_maps = NestedArray::mergeDeep($import_maps, $this->buildImportMapForExtensionNameAndPath($extension, $info->getPath()));
      }
      return $import_maps;
    }
    // Build import maps for the given theme.
    $base_theme_import_maps = \array_reduce($this->collectBaseThemes($key), fn (array $carry, string $base_theme) => NestedArray::mergeDeep($carry, $this->get($base_theme)), []);
    try {
      $theme = $this->themeHandler->getTheme($key);
    }
    catch (UnknownExtensionException) {
      return $import_maps;
    }
    $import_maps = NestedArray::mergeDeep($base_theme_import_maps, $this->buildImportMapForExtensionNameAndPath($key, $theme->getPath()));
    return $import_maps;
  }

  /**
   * Collects base themes for a given theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Array of base themes.
   */
  protected function collectBaseThemes(string $theme): array {
    $base_themes = [];
    try {
      $info = $this->themeExtensionList->getExtensionInfo($theme);
    }
    catch (UnknownExtensionException) {
      return $base_themes;
    }
    if (\array_key_exists('base theme', $info)) {
      $base_themes[] = $info['base theme'];
      $base_themes = \array_merge($base_themes, $this->collectBaseThemes($info['base theme']));
    }
    return $base_themes;
  }

  /**
   * Builds import maps for given extension name and path.
   *
   * @param string $extension
   *   Extension machine name.
   * @param string $directory
   *   Directory of the extension.
   *
   * @return array
   *   Import maps.
   *
   * @see \Drupal\Core\Asset\ImportMapManagerInterface::getImportMapForTheme()
   */
  protected function buildImportMapForExtensionNameAndPath(string $extension, string $directory): array {
    $import_maps_file = $this->rootPath . '/' . $directory . '/' . $extension . '.importmap.yml';
    if (!\file_exists($import_maps_file)) {
      return [];
    }
    $import_maps = $this->fileCache->get($import_maps_file);
    if ($import_maps === NULL) {
      $file_contents = \file_get_contents($import_maps_file);
      if ($file_contents === FALSE) {
        $this->loggerChannel->error('Error reading import maps file @file', ['@file' => $import_maps_file]);
        return [];
      }
      $import_maps = Yaml::decode($file_contents) ?? [];
      $this->fileCache->set($import_maps_file, $import_maps);
    }

    if (!\array_key_exists('imports', $import_maps) && !\array_key_exists('scopes', $import_maps)) {
      $this->loggerChannel->warning('Import maps file missing both scopes and imports keys: @file', ['@file' => $import_maps_file]);
      return [];
    }

    // Remove any irrelevant keys.
    $import_maps = \array_intersect_key($import_maps, \array_flip([
      'scopes',
      'imports',
    ]));
    if (\array_key_exists('scopes', $import_maps)) {
      $resolved_scopes = [];
      foreach ($import_maps['scopes'] as $scope => $entries) {
        $resolved_scope = \rtrim($this->resolvePath($scope, $directory, FALSE), '/') . '/';
        foreach ($entries as $name => $entry) {
          if (!\array_key_exists('path', $entry) || !is_string($entry['path'])) {
            $this->loggerChannel->warning('Missing or invalid path entry for @scope/@import entry in @file', [
              '@file' => $import_maps_file,
              '@import' => $name,
              '@scope' => $scope,
            ]);
            continue;
          }
          $resolved_scopes[$resolved_scope][$name] = $this->resolvePath($entry['path'], $directory);
        }
      }
      $import_maps['scopes'] = $resolved_scopes;
    }
    if (\array_key_exists('imports', $import_maps)) {
      $resolved_imports = [];
      foreach ($import_maps['imports'] as $name => $entry) {
        if (!\array_key_exists('path', $entry) || !is_string($entry['path'])) {
          $this->loggerChannel->warning('Missing or invalid path entry for @import entry in @file', [
            '@file' => $import_maps_file,
            '@import' => $name,
          ]);
          continue;
        }
        $resolved_imports[$name] = $this->resolvePath($entry['path'], $directory);
      }
      $import_maps['imports'] = $resolved_imports;
    }

    $import_maps = \array_filter($import_maps);

    // Allow modules and themes to alter import maps.
    $this->moduleHandler->alter('importmap', $import_maps, $extension);
    $this->themeManager->alter('importmap', $import_maps, $extension);

    return $import_maps;
  }

  /**
   * Resolves the path entry in an import or scope entry.
   *
   * The path may be:
   * - a fully formed URL, e.g. http://example.com/some.js - in this case we
   *   return the path as is. An external URL is not allowed as a key a scope
   *   entry.
   * - an absolute path, e.g. /absolute/file.js - in this case we return the
   *   path as is.
   * - a relative path e.g. js/file.js - this is relative to the module or theme
   *   that declared the import map.
   *
   * @param string $path
   *   Path to resolve.
   * @param string $directory
   *   Extension directory.
   * @param bool $allow_external
   *   TRUE if external URLS are allowed.
   *
   * @return string
   *   The resolved path.
   */
  protected function resolvePath(string $path, string $directory, bool $allow_external = TRUE): string {
    $scheme = \parse_url($path, PHP_URL_SCHEME);
    if ($scheme !== FALSE && $scheme !== 'NULL' && \in_array($scheme, UrlHelper::getAllowedProtocols(), TRUE)) {
      // External URL.
      if (!$allow_external) {
        throw new \LogicException(\sprintf('Scope keys cannot be external URLS - invalid entry %s in import map in %s', $path, $path));
      }
      return $path;
    }
    if (\substr($path, 0, 1) === '/') {
      // Absolute path.
      return $this->fileUrlGenerator->generateString($path);
    }
    // Relative to module.
    return $this->fileUrlGenerator->generateString($directory . '/' . $path);
  }

}
