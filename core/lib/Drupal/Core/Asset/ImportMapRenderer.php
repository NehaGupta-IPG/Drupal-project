<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Renders the import map.
 *
 * The import map is a <script type="importmap"> element in the head of the
 * main HTML document, mapping bare module specifiers to module URLs. Browsers
 * only support a single import map per page, so it must not be rendered for
 * other responses (e.g., Ajax responses); those can only add module preloads.
 *
 * @see \Drupal\Core\Asset\ModulePreloadRenderer
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/script/type/importmap
 */
class ImportMapRenderer implements AssetCollectionRendererInterface {

  use ImportMapUrlTrait;

  /**
   * Constructs an ImportMapRenderer.
   *
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
   *   The asset query string.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected AssetQueryStringInterface $assetQueryString,
    protected TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $import_map): array {
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
    return [
      // The 'importmap' head key is reserved for the map of the site. A page
      // supports a single import map, and a browser ignores a second one, so a
      // module or theme that needs to change the map must do so in
      // hook_importmap_alter() rather than by attaching its own
      // <script type="importmap"> element through 'html_head'.
      'importmap' => [
        '#type' => 'html_tag',
        '#tag' => 'script',
        // The JSON is safe as the #value of the script tag, which is XSS
        // filtered: Json::encode() escapes all HTML special characters as
        // \uXXXX sequences, so the filtering cannot alter the JSON, and the
        // JSON cannot break out of the script tag.
        '#value' => Json::encode($map),
        '#attributes' => ['type' => 'importmap'],
      ],
    ];
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

}
