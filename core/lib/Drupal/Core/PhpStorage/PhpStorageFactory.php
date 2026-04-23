<?php

namespace Drupal\Core\PhpStorage;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\PublicStream;

/**
 * Creates a php storage object.
 */
class PhpStorageFactory {

  /**
   * Placeholder value for the 'directory' configuration key.
   *
   * When used as a start of a directory in a storage configuration array, the
   * factory replaces it with the return value of sys_get_temp_dir(). This
   * directs compiled PHP files to the web server's local temporary directory
   * rather than the site's public files directory. This is important when the
   * public files directory is on a shared NFS mount and PHP files are compiled
   * in a race that can lead to an ever-growing number of stale files.
   */
  public const USE_SYS_TEMP = '<sys_temp>';

  /**
   * Instantiates a storage for generated PHP code.
   *
   * By default, this returns an instance of the
   * \Drupal\Component\PhpStorage\MTimeProtectedFileStorage class.
   *
   * Classes implementing
   * \Drupal\Component\PhpStorage\PhpStorageInterface can be registered for a
   * specific bin or as a default implementation.
   *
   * @param string $name
   *   The name for which the storage should be returned. Defaults to 'default'
   *   The name is also used as the storage bin if one is not specified in the
   *   configuration.
   * @param array $configuration
   *   Optional default configuration for the storage. Ignored if
   *   $settings['php_storage'][$name] is set in settings.php (site
   *   configuration always takes precedence). Recognized keys are the same as
   *   for any PhpStorageInterface implementation: 'class', 'directory', 'bin',
   *   and 'secret'.
   * @param string|null $site_path
   *   (optional) The site path to use for the storage directory. Defaults to
   *   the site's public files directory. Only necessary if the factory is used
   *   prior to the container being initialized.
   *
   * @return \Drupal\Component\PhpStorage\PhpStorageInterface
   *   An instantiated storage for the specified name.
   */
  public static function get($name, array $configuration = [], ?string $site_path = NULL) {
    $overrides = Settings::get('php_storage');
    if (isset($overrides[$name])) {
      $configuration = $overrides[$name];
    }
    elseif (empty($configuration) && isset($overrides['default'])) {
      $configuration = $overrides['default'];
    }
    // Make sure all the necessary configuration values are set.
    $class = $configuration['class'] ?? 'Drupal\Component\PhpStorage\MTimeProtectedFileStorage';
    if (!isset($configuration['secret'])) {
      $configuration['secret'] = Settings::getHashSalt();
    }
    if (!isset($configuration['bin'])) {
      $configuration['bin'] = $name;
    }
    if (isset($configuration['directory']) && str_starts_with($configuration['directory'], static::USE_SYS_TEMP)) {
      $temp_dir = static::getTempDirectory();
      if ($temp_dir) {
        $configuration['directory'] = $temp_dir . substr($configuration['directory'], strlen(static::USE_SYS_TEMP));
      }
      else {
        // Fallback to the public directory if the temporary directory cannot be
        // written to.
        unset($configuration['directory']);
      }
    }
    if (!isset($configuration['directory'])) {
      $configuration['directory'] = PublicStream::basePath($site_path) . '/php';
    }
    return new $class($configuration);
  }

  /**
   * Gets the temporary directory.
   *
   * @see \Drupal\Core\File\FileSystem::getTempDirectory())
   *
   * @return string|null
   *   The temporary directory, or NULL if it cannot be determined.
   */
  protected static function getTempDirectory(): ?string {
    // Use settings.
    $temporary_directory = Settings::get('file_temp_path');
    if (!empty($temporary_directory)) {
      return $temporary_directory;
    }

    // Fallback to OS default.
    return FileSystem::getOsTemporaryDirectory() ?: NULL;
  }

}
