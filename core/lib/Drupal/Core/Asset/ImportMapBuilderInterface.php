<?php

namespace Drupal\Core\Asset;

/**
 * Aggregates the import map of all known library definitions.
 *
 * The import map is built from the libraries of core, the enabled modules and
 * the active theme including its base themes, not just the libraries attached
 * to the current response, because browsers only support a single import map
 * per page and it cannot be updated once it has been loaded.
 *
 * @see \Drupal\Core\Asset\ImportMapBuilder
 * @see hook_importmap_alter()
 */
interface ImportMapBuilderInterface {

  /**
   * Returns the aggregated import map for the active theme.
   *
   * The map is cached, tagged with the 'library_info' cache tag, and rebuilt
   * when that tag is invalidated. Because of the caching,
   * hook_importmap_alter() implementations must be static, like
   * hook_library_info_alter().
   *
   * @return array
   *   The import map, with the keys 'imports', 'scopes' and 'integrity'.
   *   Entries under 'imports' and 'scopes' are keyed by the module specifier,
   *   and resolve to an array with the keys 'data' (the resolved path or
   *   URL), 'external' (a boolean, TRUE for external URLs), 'version' and
   *   'cache' (a boolean, FALSE for assets that must not be cached, mirroring
   *   the 'cache' key of the library definition). Entries under 'integrity'
   *   are keyed by the resolved path or URL, and resolve to an array with the
   *   same keys plus 'integrity' (the integrity metadata string).
   *
   * @see hook_importmap_alter()
   */
  public function getImportMap(): array;

  /**
   * Returns the library that provided each entry of the import map.
   *
   * This is not part of the import map itself, which is what
   * hook_importmap_alter() and the browser see. It records which library each
   * surviving entry came from, so that the module preloads can be derived from
   * the entries of the libraries that are actually attached to a response,
   * after conflicts have been merged and the map has been altered.
   *
   * @return array
   *   The provenance, keyed by section ('imports', 'scopes' and 'integrity').
   *   Entries under 'imports' are keyed by the module specifier and resolve to
   *   the name of the library that provided the entry, e.g. 'module/library'.
   *   Entries under 'scopes' are keyed by the resolved scope, and resolve to
   *   the same structure as 'imports'. Entries under 'integrity' are keyed by
   *   the resolved path or URL.
   */
  public function getProvenance(): array;

}
