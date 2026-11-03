<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Datetime\TimeInterface;
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
      // The path is part of the key, like the 'html_head_link:*' keys of
      // \Drupal\Core\Render\HtmlResponseAttachmentsProcessor, because a page
      // can preload many modules and head elements are merged by key: numbered
      // elements would overwrite each other.
      $elements['modulepreload:' . $entry['data']] = [
        '#type' => 'html_tag',
        '#tag' => 'link',
        '#attributes' => $attributes,
      ];
    }
    return $elements;
  }

}
