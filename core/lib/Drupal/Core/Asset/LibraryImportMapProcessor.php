<?php

namespace Drupal\Core\Asset;

use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;

/**
 * The default import map processor.
 *
 * Validates the import map definition against the authoring requirements of
 * the HTML specification, and resolves all of its paths, mirroring the
 * resolution of js and css asset sources. Used by
 * \Drupal\Core\Asset\LibraryDiscoveryParser.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/script/type/importmap
 * @see https://html.spec.whatwg.org/multipage/webappapis.html#import-map
 */
class LibraryImportMapProcessor implements LibraryImportMapProcessorInterface {

  /**
   * Constructs a LibraryImportMapProcessor.
   *
   * @param \Drupal\Core\Asset\LibrariesDirectoryFileFinder $librariesDirectoryFileFinder
   *   The libraries directory file finder.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $streamWrapperManager
   *   The stream wrapper manager.
   */
  public function __construct(
    protected LibrariesDirectoryFileFinder $librariesDirectoryFileFinder,
    protected StreamWrapperManagerInterface $streamWrapperManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function processImportMapDefinition(array $importmap, string $id, string $extension, string $path, int|string $version): array {
    foreach (['imports', 'scopes', 'integrity'] as $key) {
      if (isset($importmap[$key]) && !is_array($importmap[$key])) {
        throw new \UnexpectedValueException(sprintf("The '%s' key of the importmap definition of the library %s/%s must be an array.", $key, $extension, $id));
      }
    }
    foreach (array_keys($importmap) as $key) {
      if (!in_array($key, ['imports', 'scopes', 'integrity'], TRUE)) {
        throw new \UnexpectedValueException(sprintf("Unknown key '%s' in the importmap definition of the library %s/%s. Only the keys 'imports', 'scopes' and 'integrity' are allowed.", $key, $extension, $id));
      }
    }
    $processed = [
      'imports' => [],
      'scopes' => [],
      'integrity' => [],
    ];
    if (isset($importmap['imports'])) {
      $processed['imports'] = $this->processImportMapEntries($importmap['imports'], $id, $extension, $path, $version);
    }
    if (isset($importmap['scopes'])) {
      foreach ($importmap['scopes'] as $scope => $entries) {
        $scope = (string) $scope;
        if ($scope === '') {
          throw new \UnexpectedValueException(sprintf("The importmap definition of the library %s/%s contains an empty scope.", $extension, $id));
        }
        if (!is_array($entries)) {
          throw new \UnexpectedValueException(sprintf("The entries of scope '%s' in the importmap definition of the library %s/%s must be an array.", $scope, $extension, $id));
        }
        // Resolve the scope path like the other entries, so that a scope can
        // be relative to the registering extension, or an absolute path or
        // URL.
        [$resolved_scope] = $this->resolveImportMapSource($scope, $path, $id, $extension);
        $processed['scopes'][$resolved_scope] = $this->processImportMapEntries($entries, $id, $extension, $path, $version);
      }
    }
    if (isset($importmap['integrity'])) {
      foreach ($importmap['integrity'] as $url => $integrity) {
        if (!is_string($integrity) || $integrity === '') {
          throw new \UnexpectedValueException(sprintf("The integrity metadata for '%s' in the importmap definition of the library %s/%s must be a non-empty string.", $url, $extension, $id));
        }
        [$data, $external] = $this->resolveImportMapSource((string) $url, $path, $id, $extension);
        $processed['integrity'][$data] = [
          'data' => $data,
          'external' => $external,
          'version' => $version,
          'cache' => TRUE,
          'integrity' => $integrity,
        ];
      }
    }
    return $processed;
  }

  /**
   * Processes the module specifier entries of an importmap definition.
   *
   * @param array $entries
   *   The module specifier entries, keyed by the module specifier. Each value
   *   is either the source of the module, or a mapping with the keys 'path'
   *   (the source, required) and 'cache' (a boolean, defaulting to TRUE).
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
   *   The processed entries, keyed by the module specifier, and resolving to
   *   an array with the keys 'data', 'external', 'version' and 'cache'.
   *
   * @throws \UnexpectedValueException
   *   Thrown when the entries are invalid.
   */
  protected function processImportMapEntries(array $entries, string $id, string $extension, string $path, int|string $version): array {
    $processed = [];
    foreach ($entries as $specifier => $entry) {
      $specifier = (string) $specifier;
      if ($specifier === '') {
        throw new \UnexpectedValueException(sprintf("The importmap definition of the library %s/%s contains an empty module specifier.", $extension, $id));
      }
      // Defaults to TRUE, like the 'cache' option of a JavaScript asset.
      $entry_cache = TRUE;
      if (is_array($entry)) {
        if (!isset($entry['path'])) {
          throw new \UnexpectedValueException(sprintf("The value of the module specifier '%s' in the importmap definition of the library %s/%s must be a non-empty string, or a mapping with a 'path' key.", $specifier, $extension, $id));
        }
        $extra_keys = array_diff(array_keys($entry), ['path', 'cache']);
        if ($extra_keys) {
          throw new \UnexpectedValueException(sprintf("The value of the module specifier '%s' in the importmap definition of the library %s/%s has unknown keys (%s). Only the keys 'path' and 'cache' are allowed.", $specifier, $extension, $id, implode(', ', $extra_keys)));
        }
        if (array_key_exists('cache', $entry) && !is_bool($entry['cache'])) {
          throw new \UnexpectedValueException(sprintf("The 'cache' key of the module specifier '%s' in the importmap definition of the library %s/%s must be a boolean.", $specifier, $extension, $id));
        }
        $entry_cache = $entry['cache'];
        $source = $entry['path'];
      }
      else {
        $source = $entry;
      }
      if (!is_string($source) || $source === '') {
        throw new \UnexpectedValueException(sprintf("The value of the module specifier '%s' in the importmap definition of the library %s/%s must be a non-empty string.", $specifier, $extension, $id));
      }
      if (str_ends_with($specifier, '/') && !str_ends_with($source, '/')) {
        throw new \UnexpectedValueException(sprintf("The module specifier '%s' in the importmap definition of the library %s/%s ends with a slash, so its value must also end with a slash.", $specifier, $extension, $id));
      }
      [$data, $external] = $this->resolveImportMapSource($source, $path, $id, $extension);
      $processed[$specifier] = [
        'data' => $data,
        'external' => $external,
        'version' => $version,
        'cache' => $entry_cache,
      ];
    }
    return $processed;
  }

  /**
   * Resolves the source of an importmap entry to an asset path or URL.
   *
   * Mirrors the resolution of js and css asset sources: paths relative to the
   * registering extension, absolute paths relative to the Drupal root, the
   * libraries directories, stream wrapper URIs, and external URLs.
   *
   * @param string $source
   *   The source as declared in the library definition.
   * @param string $path
   *   The relative path to the extension providing the definition.
   * @param string $id
   *   The name of the library containing the definition.
   * @param string $extension
   *   The name of the extension providing the definition.
   *
   * @return array
   *   A two element array: the resolved path or URL, and a boolean which is
   *   TRUE when the resolved value is an external URL.
   */
  protected function resolveImportMapSource(string $source, string $path, string $id, string $extension): array {
    if ($source[0] === '/') {
      // A protocol-free URI (e.g., //cdn.com/example.mjs) is external.
      if (($source[1] ?? '') === '/') {
        return [$source, TRUE];
      }
      // An absolute path maps to DRUPAL_ROOT / base_path().
      $source = substr($source, 1);
      // Non core provided libraries can be in multiple locations.
      if (str_starts_with($source, 'libraries/')) {
        $path_to_source = $this->librariesDirectoryFileFinder->find(substr($source, 10));
        if ($path_to_source) {
          return [$path_to_source, FALSE];
        }
      }
      return [$source, FALSE];
    }
    // A stream wrapper URI (e.g., public://generated_js/example.mjs).
    elseif ($this->streamWrapperManager->isValidUri($source)) {
      return [$source, FALSE];
    }
    // A regular URI (e.g., https://example.com/example.mjs).
    elseif ($this->isValidUri($source)) {
      return [$source, TRUE];
    }
    // By default, file paths are relative to the registering extension.
    else {
      return [$path . '/' . $source, FALSE];
    }
  }

  /**
   * Determines if the supplied string is a valid URI.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is a URI, FALSE otherwise.
   *
   * @see \Drupal\Core\Asset\LibraryDiscoveryParser::isValidUri()
   */
  protected function isValidUri($string) {
    return count(explode('://', $string)) === 2;
  }

}
