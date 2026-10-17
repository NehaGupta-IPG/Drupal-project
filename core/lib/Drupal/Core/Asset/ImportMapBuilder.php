<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The default import map builder.
 *
 * Aggregates the import map definitions of all known library definitions into
 * the single import map that is emitted with the main HTML document, resolves
 * conflicts between extensions, and lets modules and themes alter the result.
 *
 * @see \Drupal\Core\Asset\ImportMapBuilderInterface
 * @see hook_importmap_alter()
 */
class ImportMapBuilder implements ImportMapBuilderInterface {

  /**
   * The sections of an import map, in the order they are merged and logged.
   */
  protected const SECTIONS = ['imports', 'scopes', 'integrity'];

  /**
   * The build result for this request, if it has been built already.
   *
   * @var array|null
   */
  protected ?array $result = NULL;

  /**
   * Constructs an ImportMapBuilder.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   The library discovery service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The asset chain cache backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected LibraryDiscoveryInterface $libraryDiscovery,
    protected ModuleHandlerInterface $moduleHandler,
    protected ThemeManagerInterface $themeManager,
    protected StateInterface $state,
    protected CacheBackendInterface $cache,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getImportMap(): array {
    return $this->build()['map'];
  }

  /**
   * {@inheritdoc}
   */
  public function getProvenance(): array {
    return $this->build()['provenance'];
  }

  /**
   * Returns the aggregated import map and its provenance for this request.
   *
   * @return array
   *   An array with two keys:
   *   - map: The aggregated import map, as returned by getImportMap().
   *   - provenance: The provenance, as returned by getProvenance().
   */
  protected function build(): array {
    if (isset($this->result)) {
      return $this->result;
    }
    // The import map is only built during normal site operation. Building it
    // reads the libraries of every enabled module, which invokes
    // hook_library_info_alter(), and allows modules and themes to alter the
    // result with hook_importmap_alter(). Both hooks are documented to be
    // static, and implementations may make assumptions about the installed site
    // that do not hold during installation or in maintenance mode, so the map
    // must not be built in those contexts. This is checked here, rather than at
    // the call sites, so that every consumer of the import map is covered by
    // the same policy.
    if (defined('MAINTENANCE_MODE') || $this->state->get('system.maintenance_mode')) {
      return $this->result = static::emptyResult();
    }
    $active_theme = $this->themeManager->getActiveTheme()->getName();
    $extensions = $this->getExtensions($active_theme);
    // The enabled extensions are part of the cache key, so that a module or
    // theme being installed or uninstalled is picked up even when the
    // 'library_info' cache tag is not invalidated.
    $cid = 'importmap:' . $active_theme . ':' . Crypt::hashBase64(implode(',', $extensions));
    if ($cached = $this->cache->get($cid)) {
      return $this->result = $cached->data;
    }

    $map = static::emptySections();
    $provenance = static::emptyProvenance();
    $conflicts = static::emptySections();
    foreach ($extensions as $extension) {
      foreach ($this->libraryDiscovery->getLibrariesByExtension($extension) as $name => $definition) {
        if (empty($definition['importmap'])) {
          continue;
        }
        $this->mergeImportMap($map, $provenance, $definition['importmap'], "$extension/$name", $conflicts);
      }
    }
    // Allow modules and themes to alter the aggregated import map, e.g. to
    // resolve a conflict between libraries that define the same module
    // specifier with different values. The altered map is cached, tagged with
    // 'library_info', so the hook is only invoked when the library information
    // changes; implementations must therefore be static, like
    // hook_library_info_alter(). A conflict that is not resolved (by unsetting
    // it from $conflicts) is logged as an error.
    $this->moduleHandler->alter('importmap', $map, $conflicts);
    $this->themeManager->alter('importmap', $map, $conflicts);
    $this->logImportMapConflicts($conflicts, $provenance, $map);

    // The provenance is not part of the import map the browser receives; it is
    // cached alongside it so that module preloads can be derived from the
    // altered map. The cache item is tagged with 'library_info', so conflicts
    // are logged again whenever the library information changes.
    return $this->result = [
      'map' => $map,
      'provenance' => $provenance,
    ];
  }

  /**
   * Returns an empty import map and provenance.
   *
   * @return array
   *   An array with the keys 'map' and 'provenance', both empty.
   */
  protected static function emptyResult(): array {
    return [
      'map' => static::emptySections(),
      'provenance' => static::emptyProvenance(),
    ];
  }

  /**
   * Returns an array with an empty array for each import map section.
   *
   * @return array
   *   The empty sections, keyed by section name.
   */
  protected static function emptySections(): array {
    return array_fill_keys(static::SECTIONS, []);
  }

  /**
   * Returns an array with an empty array for each provenance section.
   *
   * @return array
   *   The empty provenance, keyed by section name.
   */
  protected static function emptyProvenance(): array {
    return array_fill_keys(static::SECTIONS, []);
  }

  /**
   * Returns the extensions whose libraries contribute to the import map.
   *
   * The order determines which definition wins when two libraries define the
   * same module specifier with different values: core first, then the enabled
   * modules, then the base themes with the active theme last. The modules are
   * sorted by machine name, rather than taken in the order they were installed,
   * so that two sites with the same modules enabled resolve a conflict the
   * same way.
   *
   * @param string $active_theme
   *   The machine name of the active theme.
   *
   * @return string[]
   *   The machine names of the extensions, in merge order.
   */
  protected function getExtensions(string $active_theme): array {
    // Module scripts can be attached at any time, e.g., in an Ajax, BigPipe or
    // HTMX response, and it is not possible to know in advance which of those
    // will import a module. Browsers only support a single import map per page
    // (Firefox does not support multiple import maps), and the import map
    // cannot be updated once it has been loaded, so the complete import map has
    // to be emitted with the main HTML document. That means every known
    // extension contributes: core, the enabled modules, and the active theme
    // including its base themes.
    $modules = array_keys($this->moduleHandler->getModuleList());
    sort($modules);
    // ActiveTheme::getBaseThemeExtensions() returns the base themes starting
    // with the immediate parent and ending with the root of the chain, so the
    // order is reversed to put the most distant ancestor first. The active
    // theme comes last, and therefore has the lowest precedence.
    $themes = array_reverse(array_keys($this->themeManager->getActiveTheme()->getBaseThemeExtensions()));
    $themes[] = $active_theme;
    return array_merge(['core'], $modules, $themes);
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
   * @param array $map
   *   The aggregated import map, by reference.
   * @param array $provenance
   *   By reference. The aggregated provenance, keyed by section and key, with
   *   the name of the library that provided the entry.
   * @param array $new_map
   *   The import map of the library being merged in.
   * @param string $library
   *   The name of the library being merged in, e.g. 'module/library'.
   * @param array $conflicts
   *   By reference. The conflicts found while merging, with the same
   *   structure as passed to hook_importmap_alter().
   */
  protected function mergeImportMap(array &$map, array &$provenance, array $new_map, string $library, array &$conflicts): void {
    foreach ($new_map['imports'] as $key => $value) {
      $this->mergeImportMapEntry($map['imports'], $provenance['imports'], $conflicts['imports'], (string) $key, $value, $library);
    }
    foreach ($new_map['scopes'] as $scope => $entries) {
      $scope = (string) $scope;
      if (!isset($map['scopes'][$scope])) {
        $map['scopes'][$scope] = [];
        $provenance['scopes'][$scope] = [];
        $conflicts['scopes'][$scope] = [];
      }
      foreach ($entries as $key => $value) {
        $this->mergeImportMapEntry($map['scopes'][$scope], $provenance['scopes'][$scope], $conflicts['scopes'][$scope], (string) $key, $value, $library);
      }
    }
    foreach ($new_map['integrity'] as $key => $value) {
      $this->mergeImportMapEntry($map['integrity'], $provenance['integrity'], $conflicts['integrity'], (string) $key, $value, $library);
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
   * @param array $map
   *   The altered import map, used to check whether the entry that the merge
   *   picked is still the one that will be emitted.
   */
  protected function logImportMapConflicts(array $conflicts, array $provenance, array $map): void {
    foreach ($conflicts['imports'] ?? [] as $key => $definitions) {
      $this->logImportMapConflict((string) $key, "'imports' section", $definitions, $provenance['imports'][$key] ?? NULL, $map['imports'][$key] ?? NULL);
    }
    foreach ($conflicts['scopes'] ?? [] as $scope => $conflicts_by_key) {
      foreach ($conflicts_by_key as $key => $definitions) {
        $this->logImportMapConflict((string) $key, sprintf("'scopes' section, scope '%s'", $scope), $definitions, $provenance['scopes'][$scope][$key] ?? NULL, $map['scopes'][$scope][$key] ?? NULL);
      }
    }
    foreach ($conflicts['integrity'] ?? [] as $key => $definitions) {
      $this->logImportMapConflict((string) $key, "'integrity' section", $definitions, $provenance['integrity'][$key] ?? NULL, $map['integrity'][$key] ?? NULL);
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
   * @param mixed $definitions
   *   The definitions involved in the conflict, keyed by the name of the
   *   library that provided the definition.
   * @param string|null $winner
   *   The name of the library whose definition was used by the merge, e.g.
   *   'module/library', or NULL when the conflict was contributed by a
   *   hook_importmap_alter() implementation and has no known provenance.
   * @param mixed $used
   *   The entry that will be emitted for this key, if any. This differs from
   *   the definition the merge picked when hook_importmap_alter() replaced or
   *   removed the entry.
   */
  protected function logImportMapConflict(string $key, string $context, mixed $definitions, ?string $winner, mixed $used): void {
    if (!is_array($definitions)) {
      // A hook_importmap_alter() implementation contributed something that is
      // not a list of definitions. There is nothing useful to report per
      // library, but the malformed conflict should not break the page.
      $this->logger->error(sprintf("Import map conflict: the %s contains a malformed conflict for '%s'. A hook_importmap_alter() implementation added a conflict that is not a list of definitions keyed by library name.", $context, $key));
      return;
    }
    if ($winner === NULL) {
      // Without provenance there is no way to say which definition the merged
      // map uses, and no definition to blame. Report the key only.
      $this->logger->error(sprintf("Import map conflict: the module specifier '%s' in the %s was reported by hook_importmap_alter(), but none of the merged library definitions provides it. Resolve the conflict in hook_importmap_alter() by unsetting it from \$conflicts.", $key, $context));
      return;
    }
    // hook_importmap_alter() may have replaced or removed the entry the merge
    // picked. Say so, rather than claiming a value is used that is not.
    if ($used === NULL) {
      $used_sentence = sprintf('The value from %s was removed by hook_importmap_alter().', $winner);
    }
    elseif ($used === ($definitions[$winner] ?? NULL)) {
      $used_sentence = sprintf('The value from %s is used.', $winner);
    }
    else {
      $used_sentence = sprintf('The value from %s was replaced by hook_importmap_alter().', $winner);
    }
    foreach (array_keys($definitions) as $library) {
      if ($library === $winner) {
        continue;
      }
      $this->logger->error(sprintf("Import map conflict: the module specifier '%s' in the %s is defined by both %s and %s with different values. %s Remove the duplicate definition from one of the libraries, or resolve the conflict in hook_importmap_alter().", $key, $context, $winner, $library, $used_sentence));
    }
  }

}
