<?php

declare(strict_types=1);

namespace Drupal\update;

use Composer\InstalledVersions;

/**
 * Gets information from composer about installed packages.
 *
 * This class requires no state and has no dependencies. All the methods are
 * public static, so it is not registered as a service, doesn't need to be
 * injected elsewhere, etc.
 */
class ComposerHelper {

  /**
   * Finds the 'name' property from a composer.json file at a given path.
   *
   * @param string $composer_json_path
   *   The full path to the composer.json file to look in.
   *
   * @return string|null
   *   The value of the 'name' property if found, or NULL if the given file does
   *   not exist, could not be opened, could not be parsed, or does not define a
   *   'name' property at all.
   */
  public static function getPackageName(string $composer_json_path): ?string {
    if (!file_exists($composer_json_path)) {
      return NULL;
    }
    $composer_info = json_decode(file_get_contents($composer_json_path));
    return $composer_info?->name;
  }

  /**
   * Returns the installed version of a given package.
   *
   * @param string $package_name
   *   The composer package name to check, for example 'drupal/address'.
   *
   * @return string|null
   *   The 'pretty' version number if known, or NULL if the package is not
   *   installed.
   */
  public static function getInstalledVersion(string $package_name): ?string {
    return InstalledVersions::isInstalled($package_name) ?
      InstalledVersions::getPrettyVersion($package_name) : NULL;
  }

}
