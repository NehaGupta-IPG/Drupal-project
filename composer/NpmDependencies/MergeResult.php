<?php

namespace Drupal\Composer\NpmDependencies;

/**
 * Result object containing the outcome of merging package.json dependencies.
 *
 * This class holds the merged dependencies, information about resolved
 * conflicts, and any errors that occurred during the merge process.
 *
 * @internal
 */
final class MergeResult {

  /**
   * Merged dependencies with their resolved version constraints.
   *
   * @var array<string, string>
   *   Keyed by package name, value is the resolved version constraint.
   */
  private array $dependencies = [];

  /**
   * Merged devDependencies with their resolved version constraints.
   *
   * @var array<string, string>
   *   Keyed by package name, value is the resolved version constraint.
   */
  private array $devDependencies = [];

  /**
   * Sources for each dependency (for _drupalSources metadata).
   *
   * @var array<string, DependencySource[]>
   *   Keyed by package name, value is array of all sources for that package.
   */
  private array $sources = [];

  /**
   * Resolved conflicts with details about the resolution.
   *
   * @var array<string, array{chosen: string, sources: DependencySource[], strategy: string}>
   */
  private array $resolvedConflicts = [];

  /**
   * Errors that occurred during merging.
   *
   * @var string[]
   */
  private array $errors = [];

  /**
   * Warnings generated during merging.
   *
   * @var string[]
   */
  private array $warnings = [];

  /**
   * Adds a dependency to the merged result.
   *
   * @param string $packageName
   *   The npm package name.
   * @param string $versionConstraint
   *   The resolved version constraint.
   * @param \Drupal\Composer\NpmDependencies\DependencySource $source
   *   The source of this dependency.
   * @param bool $isDevDependency
   *   Whether this is a devDependency.
   */
  public function addDependency(
    string $packageName,
    string $versionConstraint,
    DependencySource $source,
    bool $isDevDependency = FALSE,
  ): void {
    if ($isDevDependency) {
      $this->devDependencies[$packageName] = $versionConstraint;
    }
    else {
      $this->dependencies[$packageName] = $versionConstraint;
    }

    $this->sources[$packageName][] = $source;
  }

  /**
   * Records a resolved conflict.
   *
   * @param string $packageName
   *   The package that had conflicting versions.
   * @param string $chosenVersion
   *   The version constraint that was chosen.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources that contributed to this conflict.
   * @param string $strategy
   *   The strategy used to resolve the conflict.
   */
  public function addResolvedConflict(
    string $packageName,
    string $chosenVersion,
    array $sources,
    string $strategy,
  ): void {
    $this->resolvedConflicts[$packageName] = [
      'chosen' => $chosenVersion,
      'sources' => $sources,
      'strategy' => $strategy,
    ];
  }

  /**
   * Adds an error message.
   *
   * @param string $message
   *   The error message.
   */
  public function addError(string $message): void {
    $this->errors[] = $message;
  }

  /**
   * Adds a warning message.
   *
   * @param string $message
   *   The warning message.
   */
  public function addWarning(string $message): void {
    $this->warnings[] = $message;
  }

  /**
   * Gets the merged dependencies.
   *
   * @return array<string, string>
   *   Dependencies keyed by package name.
   */
  public function getDependencies(): array {
    ksort($this->dependencies);
    return $this->dependencies;
  }

  /**
   * Gets the merged devDependencies.
   *
   * @return array<string, string>
   *   DevDependencies keyed by package name.
   */
  public function getDevDependencies(): array {
    ksort($this->devDependencies);
    return $this->devDependencies;
  }

  /**
   * Gets the sources for all dependencies.
   *
   * @return array<string, DependencySource[]>
   *   Sources keyed by package name.
   */
  public function getSources(): array {
    return $this->sources;
  }

  /**
   * Gets the resolved conflicts.
   *
   * @return array<string, array{chosen: string, sources: DependencySource[], strategy: string}>
   *   Resolved conflicts keyed by package name.
   */
  public function getResolvedConflicts(): array {
    return $this->resolvedConflicts;
  }

  /**
   * Gets all errors.
   *
   * @return string[]
   *   Array of error messages.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Gets all warnings.
   *
   * @return string[]
   *   Array of warning messages.
   */
  public function getWarnings(): array {
    return $this->warnings;
  }

  /**
   * Checks if the merge was successful (no errors).
   *
   * @return bool
   *   TRUE if no errors occurred.
   */
  public function isSuccessful(): bool {
    return empty($this->errors);
  }

  /**
   * Checks if any conflicts were resolved.
   *
   * @return bool
   *   TRUE if conflicts were resolved.
   */
  public function hasConflicts(): bool {
    return !empty($this->resolvedConflicts);
  }

  /**
   * Gets the total number of dependencies (regular + dev).
   *
   * @return int
   *   Total dependency count.
   */
  public function getTotalDependencyCount(): int {
    return count($this->dependencies) + count($this->devDependencies);
  }

  /**
   * Generates the _drupalSources metadata for package.json.
   *
   * @return array<string, array<string, string>>
   *   Metadata showing which modules contributed each dependency.
   */
  public function generateSourcesMetadata(): array {
    $metadata = [];
    foreach ($this->sources as $packageName => $sources) {
      $metadata[$packageName] = [];
      foreach ($sources as $source) {
        $metadata[$packageName][$source->sourceModule] = $source->versionConstraint;
      }
    }
    ksort($metadata);
    return $metadata;
  }

}
