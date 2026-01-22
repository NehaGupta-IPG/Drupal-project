<?php

namespace Drupal\Composer\NpmDependencies;

use Symfony\Component\Finder\Finder;

/**
 * Discovers package.json files in specified directories.
 *
 * Uses Symfony Finder to locate package.json files while excluding
 * node_modules directories to avoid picking up installed dependencies.
 *
 * @internal
 */
final readonly class PackageJsonFinder {

  /**
   * Constructs a PackageJsonFinder.
   *
   * @param string $projectRoot
   *   The root directory of the Drupal project.
   * @param \Drupal\Composer\NpmDependencies\NpmInstallOptions $options
   *   The npm install options containing scan paths and ignore rules.
   */
  public function __construct(
    private string $projectRoot,
    private NpmInstallOptions $options,
  ) {}

  /**
   * Checks if any valid scan paths exist.
   *
   * @return bool
   *   TRUE if at least one scan path exists on disk.
   */
  public function hasValidPaths(): bool {
    return !empty($this->getValidScanPaths());
  }

  /**
   * Finds all package.json files in the configured scan paths.
   *
   * @return \Symfony\Component\Finder\Finder
   *   A Finder instance containing all discovered package.json files.
   *
   * @throws \RuntimeException
   *   If no valid scan paths exist. Call hasValidPaths() first.
   */
  public function find(): Finder {
    $validPaths = $this->getValidScanPaths();

    if (empty($validPaths)) {
      throw new \RuntimeException('No valid scan paths exist. Call hasValidPaths() first.');
    }

    $finder = new Finder();
    $finder->files()
      ->name('package.json')
      // Exclude node_modules directories to avoid picking up installed deps.
      ->exclude('node_modules')
      // Exclude vendor directory.
      ->exclude('vendor')
      // Only direct children's package.json, not deeply nested ones.
      ->depth('< 2')
      ->in($validPaths);

    // Apply custom filter for ignored packages.
    $finder->filter(function (\SplFileInfo $file): bool {
      $relativePath = $this->getRelativePath($file->getPath());
      return !$this->options->isIgnored($relativePath);
    });

    return $finder;
  }

  /**
   * Gets the list of valid scan paths that exist on disk.
   *
   * @return string[]
   *   Array of absolute paths that exist and should be scanned.
   */
  private function getValidScanPaths(): array {
    $paths = [];
    foreach ($this->options->scanPaths as $scanPath) {
      $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . $scanPath;
      if (is_dir($absolutePath)) {
        $paths[] = $absolutePath;
      }
    }
    return $paths;
  }

  /**
   * Converts an absolute path to a path relative to the project root.
   *
   * @param string $absolutePath
   *   The absolute path.
   *
   * @return string
   *   The path relative to the project root.
   */
  private function getRelativePath(string $absolutePath): string {
    $prefix = $this->projectRoot . DIRECTORY_SEPARATOR;
    if (str_starts_with($absolutePath, $prefix)) {
      return substr($absolutePath, strlen($prefix));
    }
    return $absolutePath;
  }

  /**
   * Parses a package.json file and extracts dependencies.
   *
   * @param \SplFileInfo $file
   *   The package.json file to parse.
   *
   * @return array{dependencies: DependencySource[], devDependencies: DependencySource[]}
   *   An array with 'dependencies' and 'devDependencies' keys, each containing
   *   DependencySource objects.
   *
   * @throws \RuntimeException
   *   If the file cannot be read or contains invalid JSON.
   */
  public function parsePackageJson(\SplFileInfo $file): array {
    $content = file_get_contents($file->getPathname());
    if ($content === FALSE) {
      throw new \RuntimeException(sprintf('Could not read file: %s', $file->getPathname()));
    }

    $data = json_decode($content, TRUE);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \RuntimeException(sprintf(
        'Invalid JSON in %s: %s',
        $file->getPathname(),
        json_last_error_msg()
      ));
    }

    $relativePath = $this->getRelativePath($file->getPathname());
    $result = [
      'dependencies' => [],
      'devDependencies' => [],
    ];

    // Parse regular dependencies.
    if (isset($data['dependencies']) && is_array($data['dependencies'])) {
      foreach ($data['dependencies'] as $packageName => $version) {
        $result['dependencies'][] = DependencySource::fromPackageJson(
          $packageName,
          $version,
          $relativePath,
          FALSE,
        );
      }
    }

    // Parse devDependencies if configured to include them.
    if ($this->options->includeDevDependencies && isset($data['devDependencies']) && is_array($data['devDependencies'])) {
      foreach ($data['devDependencies'] as $packageName => $version) {
        $result['devDependencies'][] = DependencySource::fromPackageJson(
          $packageName,
          $version,
          $relativePath,
          TRUE,
        );
      }
    }

    return $result;
  }

}
