<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Produces code for the PackageExtensionData file.
 *
 * @internal
 */
class GeneratePackageExtensionData {

  /**
   * Gets the code for the PackageExtensionData class.
   *
   * @param \Composer\Package\PackageInterface $root_package
   *   The root package.
   * @param \Composer\Repository\InstalledRepositoryInterface $repository
   *   The local installed repository.
   *
   * @return string
   *   The PHP code to write to the PackageExtensionData class.
   */
  public static function getCode(PackageInterface $root_package, InstalledRepositoryInterface $repository): string {
    $excludedPackages = (array) ($root_package->getExtra()['drupal']['exclude-package'] ?? []);

    $providers = [];
    foreach ($repository->getPackages() as $package) {
      $extra = $package->getExtra();
      // Contrib modules are also installed packages, some may be hybrid.
      if (!isset($extra['drupal']['service-providers'])) {
        continue;
      }
      if (in_array($package->getName(), $excludedPackages, TRUE)) {
        // Allow packages to be skipped.
        continue;
      }

      if (isset($extra['drupal']['service-providers'])) {
        $packageProviders = (array) $extra['drupal']['service-providers'];
        foreach ($packageProviders as $index => $provider) {
          $providers[$package->getName() . (count($packageProviders) > 1 ? ':' . $index : '')] = $provider;
        }
      }

    }

    $template = <<<EOF
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
      class PackageExtensionData {

        public static function getProviders(): array {
          return %providers%;
        }

      }

      EOF;

    return strtr($template, [
      '%providers%' => var_export($providers, TRUE),
    ]);

  }

}
