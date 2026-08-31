<?php

namespace Drupal\Core\Asset;

/**
 * Interface defining a service that renders an import map to a render array.
 */
interface ImportMapRendererInterface {

  /**
   * Renders the import map and its module preloads.
   *
   * @param array $import_map
   *   The import map, as returned by
   *   \Drupal\Core\Asset\AssetResolverInterface::getImportMap().
   * @param array $preloads
   *   The import map entries to preload, as returned by
   *   \Drupal\Core\Asset\AssetResolverInterface::getImportMapPreloads().
   *
   * @return array
   *   A render array, keyed for use in the 'head' placeholder. It contains a
   *   <script type="importmap"> element, followed by a
   *   <link rel="modulepreload"> element for each entry of $preloads. If
   *   $import_map is empty, an empty array is returned.
   */
  public function render(array $import_map, array $preloads): array;

}
