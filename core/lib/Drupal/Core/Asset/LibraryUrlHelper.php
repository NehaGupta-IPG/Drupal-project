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
   * @param LibraryDiscoveryInterface $libraryDiscovery $
   */
  public function __construct(
    private LibraryDiscoveryInterface $libraryDiscovery,
  ) {}

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
