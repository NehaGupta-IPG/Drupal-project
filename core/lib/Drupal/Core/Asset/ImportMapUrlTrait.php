<?php

namespace Drupal\Core\Asset;

/**
 * Provides methods to render the URLs of import map data.
 *
 * @property \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
 *   The file URL generator.
 * @property \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
 *   The asset query string.
 */
trait ImportMapUrlTrait {

  /**
   * Returns the URL for an import map entry.
   *
   * The query string is the same as for unaggregated JavaScript assets, so
   * that a file that is both a JavaScript asset and an import map entry
   * resolves to the same URL, and the browser reuses the same cache entry.
   *
   * @param array $entry
   *   An import map entry, with the keys 'data', 'external' and 'version'.
   *
   * @return string
   *   The URL of the mapped module.
   */
  protected function importMapEntryUrl(array $entry): string {
    if ($entry['external']) {
      return $entry['data'];
    }
    $url = $this->fileUrlGenerator->generate($entry['data'])->toString();
    $query_string = $entry['version'] == -1 ? $this->assetQueryString->get() : 'v=' . $entry['version'];
    $query_string_separator = str_contains($entry['data'], '?') ? '&' : '?';
    return $url . $query_string_separator . $query_string;
  }

  /**
   * Returns the URL for an import map scope key.
   *
   * Unlike the values of the import map, the scope key does not get a query
   * string: the browser prefix-matches the scope against the resolved module
   * URL, which includes the query string, so a scope with a query string
   * would never match.
   *
   * @param string $scope
   *   A resolved scope source: a path relative to the Drupal root, or an
   *   external URL.
   *
   * @return string
   *   The absolute URL of the scope.
   */
  protected function importMapScopeUrl(string $scope): string {
    // External scope URLs are used as-is.
    if (str_starts_with($scope, 'http://') || str_starts_with($scope, 'https://')) {
      return $scope;
    }
    return $this->fileUrlGenerator->generate($scope)->toString();
  }

}
