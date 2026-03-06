<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Produces code for the DrupalInstalled file.
 *
 * @internal
 */
class DrupalInstalledTemplate {

  /**
   * Gets the code for the DrupalInstalled class.
   *
   * @param \Composer\Package\PackageInterface $root_package
   *   The root package.
   * @param \Composer\Repository\InstalledRepositoryInterface $repository
   *   The local installed repository.
   * @param string $absolute_app_root
   *   The absolute path to the app root where Drupal is installed.
   * @param int $trim_count_from_installed_file
   *   The number of path components to remove from DrupalInstalled file's
   *   __DIR__ to get to the project root.
   *
   * @return string
   *   The PHP code to write to the DrupalInstalled class.
   */
  public static function getCode(PackageInterface $root_package, InstalledRepositoryInterface $repository, string $relative_web_root, int $trim_count_from_installed_file): string {
    // Ensure the packages are sorted consistently.
    $packages = $repository->getPackages();
    usort($packages, static function (PackageInterface $a, PackageInterface $b) {
      return $a->getUniqueName() <=> $b->getUniqueName();
    });

    // Write out a hash of the version information to a file so we can use it.
    $versions = array_reduce($packages, fn (string $carry, PackageInterface $package) => $carry . $package->getUniqueName() . '-' . $package->getSourceReference() . '|', '');
    // Add the root_package package version info so custom code changes and
    // root_package package version changes result in the hash changing.
    $versions .= $root_package->getUniqueName() . '-' . $root_package->getSourceReference();
    $version_hash = hash('xxh3', $versions);
    return <<<EOF
      <?php

      namespace Drupal;

      /**
       * A class containing information determined during composer installation.
       *
       * This file is generated automatically by the
       * drupal/core-composer-scaffold Composer plugin, and should not be
       * edited.
       *
       * @see \Drupal\Composer\Plugin\Scaffold\Plugin::preAutoloadDump()
       */
      class DrupalInstalled {

        /**
         * The absolute path to the Drupal installation.
         */
        protected static string \$app_root;

        /**
         * A hash of all the installed packages and their versions.
         */
        public const string VERSIONS_HASH = '$version_hash';

        /**
         * Gets the absolute path to the Drupal installation.
         *
         * This is the directory containing the scaffolded index.php file which is
         * Drupal's web entry point.
         *
         * This must use the __DIR__ magic constant so that the codebase is
         * portable.
         */
        public static function getAppRoot() {
          if (empty(static::\$app_root)) {
            static::\$app_root = dirname(__DIR__, $trim_count_from_installed_file) . '/' . '$relative_web_root';
          }

          return static::\$app_root;
        }

      }

      EOF;
  }

}
