<?php

namespace Drupal\Core\Asset;

/**
 * Interface defining a service that dumps an asset to a specified location.
 */
interface AssetDumperUriInterface extends AssetDumperInterface {

  /**
   * Dumps an (optimized) asset to persistent storage.
   *
   * @param string $data
   *   The asset's contents.
   * @param string $file_extension
   *   The file extension of this asset.
   * @param string $uri
   *   The URI to dump to.
   *
   * @return string
   *   An URI to access the dumped asset, or an empty string if the asset could
   *   not be saved.
   */
  public function dumpToUri(string $data, string $file_extension, string $uri): string;

}
