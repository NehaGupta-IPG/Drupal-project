<?php

declare(strict_types=1);

namespace Drupal\Core\Extension;

use Symfony\Component\Filesystem\Path;

/**
 * Information about php package drupal extensions the kernel needs.
 *
 * @see \Drupal\Core\DrupalKernel::getPhpPackageExtensions()
 */
final class PhpPackageExtensions {

  /**
   * The cached details.
   */
  private ?array $details = NULL;

  /**
   * Constructs a new PhpPackageExtensions.
   *
   * @param array $installedPackages
   *   The decoded 'packages' list from vendor/composer/installed.json.
   * @param string[] $excludedPackages
   *   Package names to skip.
   * @param string $vendorComposerDir
   *   The vendor/composer directory. Composer records relative paths to this.
   * @param string $appRoot
   *   The Drupal application root for relative namespace paths.
   */
  public function __construct(
    private readonly array $installedPackages,
    private readonly array $excludedPackages,
    private readonly string $vendorComposerDir,
    private readonly string $appRoot,
  ) {}

  /**
   * Builds a PhpPackageExtensions from the real composer files on disk.
   *
   * @param string $vendorComposerDir
   *   The vendor/composer directory where installed.json is.
   * @param string $projectRoot
   *   The directory containing the root composer.json.
   * @param string $appRoot
   *   The Drupal application root for relative namespace paths.
   *
   * @return self
   *   The constructed PhpPackageExtensions.
   */
  public static function fromFilePaths(string $vendorComposerDir, string $projectRoot, string $appRoot): self {
    $installed_json = $vendorComposerDir . DIRECTORY_SEPARATOR . 'installed.json';
    assert(is_file($installed_json));
    $installed = json_decode((string) file_get_contents($installed_json), TRUE, flags: JSON_THROW_ON_ERROR);

    // Filter the installed packages to keep the list shorter.
    $packages = array_filter($installed['packages'] ?? [], fn($package) => isset($package['extra']['drupal']));

    $composer_json = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
    assert(is_file($composer_json));
    $data = json_decode((string) file_get_contents($composer_json), TRUE, flags: JSON_THROW_ON_ERROR);
    $excluded = (array) ($data['extra']['drupal']['exclude-package'] ?? []);

    return new self($packages, $excluded, $vendorComposerDir, $appRoot);
  }

  /**
   * Gets the container_yamls from php packages.
   *
   * @return string[]
   *   The absolute paths to the container_yamls.
   */
  public function getContainerYamls(): array {
    return $this->parse()['container_yamls'];
  }

  /**
   * Gets the service providers from php packages.
   *
   * @return string[]
   *   The service provider class names.
   */
  public function getProviders(): array {
    return $this->parse()['providers'];
  }

  /**
   * Gets the PSR-4 namespaces from php packages.
   *
   * @return array
   *   The namespace paths.
   */
  public function getNamespaces(): array {
    return $this->parse()['namespaces'];
  }

  /**
   * Parses the installed packages into their extension metadata.
   *
   * @return array
   *   The parsed details, cached for subsequent calls.
   */
  private function parse(): array {
    if ($this->details !== NULL) {
      return $this->details;
    }

    $details = [
      'container_yamls' => [],
      'providers' => [],
      'namespaces' => [],
    ];
    foreach ($this->installedPackages as $package) {
      // Contrib modules are also installed packages, some may be hybrid.
      if (!isset($package['extra']['drupal']['container_yamls']) && !isset($package['extra']['drupal']['providers'])) {
        // @todo decide what packages need to do to be considered extensions.
        continue;
      }
      if (in_array($package['name'], $this->excludedPackages, TRUE)) {
        // Allow packages to be skipped.
        continue;
      }
      // The path to the package so that various files can be found.
      $package_path = Path::canonicalize($this->vendorComposerDir . DIRECTORY_SEPARATOR . $package['install-path']) . DIRECTORY_SEPARATOR;

      if (isset($package['extra']['drupal']['container_yamls'])) {
        $files = (array) $package['extra']['drupal']['container_yamls'];
        foreach ($files as $index => $file) {
          $details['container_yamls'][$package['name'] . (count($files) > 1 ? ':' . $index : '')] = $package_path . $file;
        }
      }

      if (isset($package['extra']['drupal']['providers'])) {
        $providers = (array) $package['extra']['drupal']['providers'];
        foreach ($providers as $index => $provider) {
          $details['providers'][$package['name'] . (count($providers) > 1 ? ':' . $index : '')] = $provider;
        }
      }

      // We only support packages with psr-4 namespaces.
      if (isset($package['autoload']['psr-4'])) {
        foreach ($package['autoload']['psr-4'] as $namespace => $psr4s) {
          // Most of the time this will be only one directory for the
          // namespace. But composer allows an array and it seems that
          // Drupal is fine with an array too.
          $relative = [];
          foreach ((array) $psr4s as $psr4) {
            $relative[] = Path::makeRelative($package_path . $psr4, $this->appRoot);
          }
          if (count($relative) === 1) {
            $relative = current($relative);
          }
          // container.namespaces keys never have the trailing namespace
          // separator, but composer.json's psr-4 keys always do.
          $details['namespaces'][rtrim($namespace, '\\')] = $relative;
        }
      }
    }

    return $this->details = $details;
  }

}
