<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\SymfonyConfigShim;

/**
 * Stub for \Symfony\Component\Config\FileLocatorInterface.
 *
 * @internal
 */
interface FileLocatorInterface {

  /**
   * Returns a full path for a given file name.
   *
   * @param string $name
   *   The file name to locate.
   * @param string|null $currentPath
   *   The current path.
   * @param bool $first
   *   Whether to return the first occurrence or an array of file names.
   *
   * @return string|string[]
   *   The full path to the file or an array of file paths.
   */
  public function locate(string $name, ?string $currentPath = NULL, bool $first = TRUE): string|array;

}
