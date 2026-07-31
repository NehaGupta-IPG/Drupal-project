<?php

namespace Drupal\Core\Asset;

use Drupal\Component\Utility\UrlHelper;

/**
 * A Url helper implementation for (un)compressing library information.
 */
readonly class LibraryUrlHelper {

  /**
   * Constructs a new LibraryUrlHelper.
   *
   * @param LibraryDiscoveryInterface $libraryDiscovery
   *   The library discovery service.
   */
  public function __construct(
    private LibraryDiscoveryInterface $libraryDiscovery,
  ) {}

  /**
   * Compresses the library string to a lookup map imploded to a string.
   *
   * We take the libraries and create a lookup map where the indices of the
   * library are replacing the names which will result in a much smaller
   * string to be compressed by UrlHelper::compressQueryParameter.
   *
   * If no index can be found the original name will be appended to the string
   * ensuring no library is lost.
   *
   * @param string $data
   *   The data that will be compressed
   *
   * @return string
   *   A string of libraries.
   */
  public function compress(string $data): string {
    // To reduce the length of the ajax_page_state parameter, replace library
    // names with their index from the list of libraries for that extension.
    $map = [];
    foreach (explode(',', $data) as $library) {
      [$extension, $name] = explode('/', $library, 2);
      $extension_library_names = array_keys(
        $this->libraryDiscovery->getLibrariesByExtension($extension)
      );
      $index = array_search($name, $extension_library_names);

      // Keep the original name if the index can not be determined.
      // For example when a non-existing library is referenced.
      $map[$extension][] = $index === FALSE ? $name : $index;
    }

    $data = [];
    foreach ($map as $extension => $indices) {
      $data[] = $extension . '/' . implode('+', $indices);
    }

    $data = implode(',', $data);

    return UrlHelper::compressQueryParameter($data);
  }

  /**
   * Uncompress a library string that was compressed using indices.
   *
   * We get a string with extensions and library indices and translate them
   * back to a string with library names.
   *
   * If the index is not a number it is probably a library name so we will treat
   * it as such when appending it to the map.
   *
   * @param string $compressed
   *   The string that will be uncompressed.
   *
   * @return string
   *   An uncompressed string of libraries.
   */
  public function uncompress(string $compressed): string {
    $uncompressed = UrlHelper::uncompressQueryParameter($compressed);

    // To reduce the length of the ajax_page_state parameter, the library
    // names are replaced with their index from the list of libraries for that
    // extension. The indexes must be transformed back into names.
    $map = [];
    foreach (explode(',', $uncompressed) as $library) {
      [$extension, $items] = explode('/', $library, 2);
      $extension_library_names = array_keys(
        $this->libraryDiscovery->getLibrariesByExtension($extension)
      );

      foreach (explode('+', $items) as $item) {
        // When dealing with an index, lookup the library name, otherwise treat
        // the item as a library name.
        $map[] = (int) $item == $item
          ? $extension . '/' . $extension_library_names[$item]
          : $extension . '/' . $item;
      }
    }

    return implode(',', $map);
  }

}
