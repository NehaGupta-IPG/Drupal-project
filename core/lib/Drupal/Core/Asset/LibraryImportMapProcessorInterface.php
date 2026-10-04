<?php

namespace Drupal\Core\Asset;

/**
 * Processes the importmap key of a library definition.
 *
 * Validates the definition against the import map authoring requirements of
 * the HTML specification, and resolves all of its paths.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script/type/importmap
 * @see https://html.spec.whatwg.org/multipage/webappapis.html#import-map
 */
interface LibraryImportMapProcessorInterface {

  /**
   * Processes the importmap key of a library definition.
   *
   * Validates the definition against the import map authoring requirements of
   * the HTML specification, and resolves all of its paths, mirroring the
   * resolution of js and css asset sources.
   *
   * @param array $importmap
   *   The importmap definition, as declared in the library definition. It may
   *   contain the keys 'imports', 'scopes' and 'integrity'.
   * @param string $id
   *   The name of the library containing the definition.
   * @param string $extension
   *   The name of the extension providing the definition.
   * @param string $path
   *   The relative path to the extension providing the definition.
   * @param int|string $version
   *   The version of the library containing the definition, or -1 if the
   *   library has no version.
   *
   * @return array
   *   The processed importmap definition, with the keys 'imports', 'scopes'
   *   and 'integrity'. Entries under 'imports' and 'scopes' are keyed by the
   *   module specifier, and resolve to an array with the keys 'data' (the
   *   resolved path or URL), 'external' (a boolean, TRUE for external URLs)
   *   and 'version'. Entries under 'integrity' are keyed by the resolved path
   *   or URL, and resolve to an array with the keys 'data', 'external',
   *   'version' and 'integrity' (the integrity metadata string).
   *
   * @throws \UnexpectedValueException
   *   Thrown when the importmap definition is invalid.
   *
   * @see https://html.spec.whatwg.org/multipage/webappapis.html#import-map
   */
  public function processImportMapDefinition(array $importmap, string $id, string $extension, string $path, int|string $version): array;

}
