<?php

namespace Drupal\Component\FileSystem;

// cspell:ignore winnt

/**
 * Provides file system functions.
 */
class FileSystem {

  /**
   * Discovers a writable system-appropriate temporary directory.
   *
   * @return string|false
   *   A string containing the path to the temporary directory, or FALSE if no
   *   suitable temporary directory can be found.
   */
  public static function getOsTemporaryDirectory(bool $local_only = FALSE): string {
    $directories = [];

    // Has PHP been set with an upload_tmp_dir?
    if (ini_get('upload_tmp_dir')) {
      $directories[] = ini_get('upload_tmp_dir');
    }

    // PHP may be able to find an alternative tmp directory.
    $directories[] = sys_get_temp_dir();

    // Operating system specific dirs.
    if (str_starts_with(PHP_OS, 'WIN')) {
      $directories[] = 'c:\\windows\\temp';
      $directories[] = 'c:\\winnt\\temp';
    }
    else {
      $directories[] = '/tmp';
    }

    foreach ($directories as $directory) {
      if (is_dir($directory) && is_writable($directory) && (!$local_only || !static::getScheme($directory))) {
        // Both sys_get_temp_dir() and ini_get('upload_tmp_dir') can return
        // paths with a trailing directory separator.
        return rtrim($directory, DIRECTORY_SEPARATOR);
      }
    }
    return FALSE;
  }

  /**
   * Returns the scheme of a URI (e.g. a stream).
   *
   * @param string $uri
   *   A stream, referenced as "scheme://target" or "data:target".
   *
   * @return string|false
   *   A string containing the name of the scheme, or FALSE if none. For
   *   example, the URI "public://example.txt" would return "public".
   *
   * @see \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface::getTarget()
   */
  public static function getScheme(string $uri): string|FALSE {
    if (preg_match('/^([\w\-]+):\/\/|^(data):/', $uri, $matches)) {
      // The scheme will always be the last element in the matches array.
      return array_pop($matches);
    }

    return FALSE;
  }

}
