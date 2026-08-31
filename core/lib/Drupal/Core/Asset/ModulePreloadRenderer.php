<?php

namespace Drupal\Core\Asset;

use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Renders module preloads.
 *
 * Module preloads are <link rel="modulepreload"> elements, instructing the
 * browser to fetch the given module, so that module scripts that import it
 * can start executing as soon as possible. Unlike the import map, module
 * preloads may be added to a page multiple times, e.g., in Ajax responses.
 *
 * @see \Drupal\Core\Asset\ImportMapRenderer
 * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/rel/modulepreload
 */
class ModulePreloadRenderer implements AssetCollectionRendererInterface {

  use ImportMapUrlTrait;

  /**
   * Constructs a ModulePreloadRenderer.
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
  public function render(array $preloads): array {
    $elements = [];
    foreach ($preloads as $entry) {
      $attributes = [
        'rel' => 'modulepreload',
        'href' => $this->importMapEntryUrl($entry),
      ];
      // A module fetch from another origin is a CORS request, so the preload
      // must be as well, in order to use the same cache entry.
      if ($entry['external']) {
        $attributes['crossorigin'] = 'anonymous';
      }
      $elements['modulepreload:' . $entry['data']] = [
        '#type' => 'html_tag',
        '#tag' => 'link',
        '#attributes' => $attributes,
      ];
    }
    return $elements;
  }

}
