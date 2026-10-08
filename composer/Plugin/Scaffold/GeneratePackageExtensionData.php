<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Installer\InstallationManager;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Symfony\Component\Filesystem\Path;

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
   * @param \Composer\Installer\InstallationManager $installationManager
   *   The installation manager, used to find the install path of packages.
   * @param string $appRoot
   *   The Drupal application root, for relative namespace paths.
   *
   * @return string
   *   The PHP code to write to the PackageExtensionData class.
   */
  public static function getCode(PackageInterface $root_package, InstalledRepositoryInterface $repository, InstallationManager $installationManager, string $appRoot): string {
    $excludedPackages = (array) ($root_package->getExtra()['drupal']['exclude-package'] ?? []);

    $namespaces = [];
    $services = [];
    $providers = [];
    foreach ($repository->getPackages() as $package) {
      $extra = $package->getExtra();
      // Contrib modules are also installed packages, some may be hybrid.
      if (!isset($extra['drupal']['extension'])) {
        continue;
      }
      if (in_array($package->getName(), $excludedPackages, TRUE)) {
        // Allow packages to be skipped.
        continue;
      }
      $config = $extra['drupal']['extension'];
      // The path to the package so that various files can be found.
      $package_path = Path::canonicalize((string) $installationManager->getInstallPath($package)) . DIRECTORY_SEPARATOR;

      if (isset($config['services_yaml'])) {
        $file = (string) $config['services_yaml'];
        $services[$package->getName()] = $package_path . $file;
      }

      if (isset($config['providers'])) {
        $packageProviders = (array) $config['providers'];
        foreach ($packageProviders as $index => $provider) {
          $providers[$package->getName() . (count($packageProviders) > 1 ? ':' . $index : '')] = $provider;
        }
      }

      if (!isset($config['auto-discover']) || $config['auto-discover'] === TRUE) {
        // We only support packages with psr-4 namespaces.
        foreach ($package->getAutoload()['psr-4'] ?? [] as $namespace => $psr4s) {
          // Most of the time this will be only one directory for the
          // namespace. But composer allows an array and it seems that
          // Drupal is fine with an array too.
          $relative = [];
          foreach ((array) $psr4s as $psr4) {
            $relative[] = Path::makeRelative($package_path . $psr4, $appRoot);
          }
          if (count($relative) === 1) {
            $relative = current($relative);
          }
          // container.namespaces keys never have the trailing namespace
          // separator, but composer.json's psr-4 keys always do.
          $namespaces[rtrim($namespace, '\\')] = $relative;
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

        public static function getNamespaces(): array {
          return %namespaces%;
        }

        public static function getServicesYamls(): array {
          return %services%;
        }

        public static function getProviders(): array {
          return %providers%;
        }

      }

      EOF;

    return strtr($template, [
      '%namespaces%' => var_export($namespaces, TRUE),
      '%services%' => var_export($services, TRUE),
      '%providers%' => var_export($providers, TRUE),
    ]);

  }

}
