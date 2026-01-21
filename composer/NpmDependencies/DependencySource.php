<?php

namespace Drupal\Composer\NpmDependencies;

/**
 * Data transfer object tracking the origin of an npm dependency.
 *
 * This class records where each npm dependency was discovered, which is
 * useful for debugging conflicts and understanding the dependency graph.
 *
 * @internal
 */
final readonly class DependencySource {

  /**
   * Constructs a DependencySource object.
   *
   * @param string $packageName
   *   The npm package name (e.g., 'lodash', '@babel/core').
   * @param string $versionConstraint
   *   The semver version constraint (e.g., '^4.17.0', '~1.2.3').
   * @param string $sourcePath
   *   The path to the package.json file where this dependency was found.
   * @param string $sourceModule
   *   The Drupal module/theme name that requires this dependency.
   * @param bool $isDevDependency
   *   Whether this is a devDependency (vs a regular dependency).
   */
  public function __construct(
    public string $packageName,
    public string $versionConstraint,
    public string $sourcePath,
    public string $sourceModule,
    public bool $isDevDependency = FALSE,
  ) {}

  /**
   * Creates a DependencySource from package.json data.
   *
   * @param string $packageName
   *   The npm package name.
   * @param string $versionConstraint
   *   The semver version constraint.
   * @param string $packageJsonPath
   *   The path to the package.json file.
   * @param bool $isDevDependency
   *   Whether this is a devDependency.
   *
   * @return self
   *   A new DependencySource instance.
   */
  public static function fromPackageJson(
    string $packageName,
    string $versionConstraint,
    string $packageJsonPath,
    bool $isDevDependency = FALSE,
  ): self {
    // Extract module name from path (e.g., 'modules/contrib/foo' -> 'foo').
    $pathParts = explode(DIRECTORY_SEPARATOR, dirname($packageJsonPath));
    $sourceModule = end($pathParts) ?: basename(dirname($packageJsonPath));

    return new self(
      $packageName,
      $versionConstraint,
      $packageJsonPath,
      $sourceModule,
      $isDevDependency,
    );
  }

  /**
   * Returns a human-readable description of the source.
   *
   * @return string
   *   A formatted string describing where this dependency came from.
   */
  public function getDescription(): string {
    $type = $this->isDevDependency ? 'devDependency' : 'dependency';
    return sprintf(
      '%s@%s (%s in %s)',
      $this->packageName,
      $this->versionConstraint,
      $type,
      $this->sourceModule
    );
  }

}
