<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Renders import maps.
 */
class ImportMapRenderer implements ImportMapRendererInterface {

  /**
   * Constructs an ImportMapRenderer.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
   *   The asset query string.
   */
  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected AssetQueryStringInterface $assetQueryString,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $import_map, array $preloads): array {
    $map = [];
    if ($import_map['imports']) {
      $map['imports'] = $this->renderImportMapEntries($import_map['imports']);
    }
    if ($import_map['scopes']) {
      $map['scopes'] = [];
      foreach ($import_map['scopes'] as $scope => $entries) {
        $map['scopes'][$this->importMapScopeUrl($scope)] = $this->renderImportMapEntries($entries);
      }
    }
    if ($import_map['integrity']) {
      $map['integrity'] = [];
      foreach ($import_map['integrity'] as $entry) {
        $map['integrity'][$this->importMapEntryUrl($entry)] = $entry['integrity'];
      }
    }
    if (empty($map)) {
      return [];
    }
    $elements = [
      'importmap' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#value' => Json::encode($map),
        '#attributes' => ['type' => 'importmap'],
      ],
    ];
    foreach ($preloads as $entry) {
      $url = $this->importMapEntryUrl($entry);
      $attributes = [
        'rel' => 'modulepreload',
        'href' => $url,
      ];
      // A module fetch from another origin is a CORS request, so the preload
      // must be as well, in order to use the same cache entry.
      if ($entry['external']) {
        $attributes['crossorigin'] = 'anonymous';
      }
      $elements['importmap:modulepreload:' . $entry['data']] = [
        '#type' => 'html_tag',
        '#tag' => 'link',
        '#attributes' => $attributes,
      ];
    }
    return $elements;
  }

  /**
   * Renders the module specifiers of an import map as URLs.
   *
   * @param array $entries
   *   The import map entries, keyed by the module specifier.
   *
   * @return array
   *   The rendered entries, keyed by the module specifier, and resolving to
   *   the URL of the mapped module.
   */
  protected function renderImportMapEntries(array $entries): array {
    $rendered = [];
    foreach ($entries as $specifier => $entry) {
      $rendered[$specifier] = $this->importMapEntryUrl($entry);
    }
    return $rendered;
  }

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
