<?php

namespace Drupal\Composer\NpmDependencies;

use Composer\Semver\Constraint\MatchNoneConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;

/**
 * Merges npm dependencies from multiple package.json files.
 *
 * Supports three conflict resolution strategies:
 * - highest-satisfies: Find the most restrictive constraint that satisfies all
 * - first-wins: Use the first encountered constraint
 * - fail: Error if constraints differ
 *
 * @internal
 */
final class PackageJsonMerger {

  /**
   * Version parser for handling semver constraints.
   */
  private VersionParser $versionParser;

  /**
   * Collected dependencies before final merge.
   *
   * @var array<string, DependencySource[]>
   */
  private array $collectedDependencies = [];

  /**
   * Collected devDependencies before final merge.
   *
   * @var array<string, DependencySource[]>
   */
  private array $collectedDevDependencies = [];

  /**
   * Constructs a PackageJsonMerger.
   *
   * @param \Drupal\Composer\NpmDependencies\NpmInstallOptions $options
   *   The npm install options.
   */
  public function __construct(
    private readonly NpmInstallOptions $options,
  ) {
    $this->versionParser = new VersionParser();
  }

  /**
   * Adds dependencies from a parsed package.json.
   *
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $dependencies
   *   Array of regular dependencies.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $devDependencies
   *   Array of dev dependencies.
   */
  public function addDependencies(array $dependencies, array $devDependencies = []): void {
    foreach ($dependencies as $source) {
      $this->collectedDependencies[$source->packageName][] = $source;
    }
    foreach ($devDependencies as $source) {
      $this->collectedDevDependencies[$source->packageName][] = $source;
    }
  }

  /**
   * Merges all collected dependencies using the configured strategy.
   *
   * @return \Drupal\Composer\NpmDependencies\MergeResult
   *   The merge result containing resolved dependencies and any conflicts.
   */
  public function merge(): MergeResult {
    $result = new MergeResult();

    // Merge regular dependencies.
    foreach ($this->collectedDependencies as $packageName => $sources) {
      $this->mergePackage($packageName, $sources, FALSE, $result);
    }

    // Merge dev dependencies.
    foreach ($this->collectedDevDependencies as $packageName => $sources) {
      // Skip if already in regular dependencies.
      if (isset($this->collectedDependencies[$packageName])) {
        $result->addWarning(sprintf(
          'Package "%s" is in both dependencies and devDependencies; using dependencies version.',
          $packageName
        ));
        continue;
      }
      $this->mergePackage($packageName, $sources, TRUE, $result);
    }

    // Clear intervals cache after processing.
    Intervals::clear();

    return $result;
  }

  /**
   * Merges a single package from multiple sources.
   *
   * @param string $packageName
   *   The npm package name.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources for this package.
   * @param bool $isDevDependency
   *   Whether this is a dev dependency.
   * @param \Drupal\Composer\NpmDependencies\MergeResult $result
   *   The result object to update.
   */
  private function mergePackage(
    string $packageName,
    array $sources,
    bool $isDevDependency,
    MergeResult $result,
  ): void {
    if (count($sources) === 1) {
      // No conflict, just add it.
      $source = reset($sources);
      $result->addDependency($packageName, $source->versionConstraint, $source, $isDevDependency);
      return;
    }

    // Multiple sources - need to resolve.
    $resolvedVersion = $this->resolveConflict($packageName, $sources, $result);

    if ($resolvedVersion !== NULL) {
      // Record the resolution with the first source for tracking.
      $result->addDependency($packageName, $resolvedVersion, $sources[0], $isDevDependency);
      $result->addResolvedConflict($packageName, $resolvedVersion, $sources, $this->options->conflictResolution);
    }
  }

  /**
   * Resolves a version conflict using the configured strategy.
   *
   * @param string $packageName
   *   The npm package name.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources with potentially different version constraints.
   * @param \Drupal\Composer\NpmDependencies\MergeResult $result
   *   The result object for recording errors.
   *
   * @return string|null
   *   The resolved version constraint, or NULL if resolution failed.
   */
  private function resolveConflict(string $packageName, array $sources, MergeResult $result): ?string {
    // Get unique version constraints.
    $constraints = [];
    foreach ($sources as $source) {
      $constraints[$source->versionConstraint] = $source;
    }

    // If all constraints are the same, no conflict.
    if (count($constraints) === 1) {
      return array_key_first($constraints);
    }

    return match ($this->options->conflictResolution) {
      'highest-satisfies' => $this->resolveHighestSatisfies($packageName, $sources, $result),
      'first-wins' => $this->resolveFirstWins($sources),
      'fail' => $this->resolveFail($packageName, $sources, $result),
      default => NULL,
    };
  }

  /**
   * Resolves using highest-satisfies strategy.
   *
   * Finds the most restrictive constraint that satisfies all requirements,
   * using semver intersection when possible.
   *
   * @param string $packageName
   *   The npm package name.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources.
   * @param \Drupal\Composer\NpmDependencies\MergeResult $result
   *   The result object.
   *
   * @return string|null
   *   The resolved constraint or NULL on failure.
   */
  private function resolveHighestSatisfies(string $packageName, array $sources, MergeResult $result): ?string {
    $constraints = array_map(fn($s) => $s->versionConstraint, $sources);

    try {
      $resolved = $this->minimizeConstraints($constraints);

      if ($this->options->verboseConflicts) {
        $sourceDescriptions = array_map(fn($s) => $s->getDescription(), $sources);
        $result->addWarning(sprintf(
          'Resolved conflict for "%s": chose "%s" from [%s]',
          $packageName,
          $resolved,
          implode(', ', $sourceDescriptions)
        ));
      }

      return $resolved;
    }
    catch (\LogicException $e) {
      $sourceDescriptions = array_map(fn($s) => $s->getDescription(), $sources);
      $result->addError(sprintf(
        'Cannot resolve conflicting constraints for "%s": %s. Sources: [%s]',
        $packageName,
        $e->getMessage(),
        implode(', ', $sourceDescriptions)
      ));
      return NULL;
    }
  }

  /**
   * Minimizes multiple version constraints to a single constraint.
   *
   * @param string[] $constraints
   *   Array of version constraint strings.
   *
   * @return string
   *   The minimized constraint.
   *
   * @throws \LogicException
   *   If constraints have no intersection.
   */
  private function minimizeConstraints(array $constraints): string {
    if (count($constraints) < 2) {
      return reset($constraints) ?: '*';
    }

    $result = array_shift($constraints);
    foreach ($constraints as $constraint) {
      $result = $this->minimizeTwoConstraints($result, $constraint);
    }

    return $result;
  }

  /**
   * Minimizes two constraints to find the most restrictive satisfying both.
   *
   * @param string $constraintA
   *   First constraint.
   * @param string $constraintB
   *   Second constraint.
   *
   * @return string
   *   The minimized constraint.
   *
   * @throws \LogicException
   *   If constraints have no intersection.
   */
  private function minimizeTwoConstraints(string $constraintA, string $constraintB): string {
    // Handle npm-specific constraints by normalizing them.
    $constraintA = $this->normalizeNpmConstraint($constraintA);
    $constraintB = $this->normalizeNpmConstraint($constraintB);

    try {
      $objectA = $this->versionParser->parseConstraints($constraintA);
      $objectB = $this->versionParser->parseConstraints($constraintB);
    }
    catch (\UnexpectedValueException $e) {
      // If we can't parse, fall back to first constraint.
      return $constraintA;
    }

    if (Intervals::isSubsetOf($objectA, $objectB)) {
      return $constraintA;
    }
    if (Intervals::isSubsetOf($objectB, $objectA)) {
      return $constraintB;
    }

    // Try to compute intersection.
    $compacted = Intervals::compactConstraint(new MultiConstraint([$objectA, $objectB]));
    if ($compacted instanceof MatchNoneConstraint) {
      throw new \LogicException(sprintf(
        'The constraints "%s" and "%s" do not intersect.',
        $constraintA,
        $constraintB
      ));
    }

    return sprintf(
      '%s%s, %s%s',
      $compacted->getLowerBound()->isInclusive() ? '>=' : '>',
      $compacted->getLowerBound()->getVersion(),
      $compacted->getUpperBound()->isInclusive() ? '<=' : '<',
      $compacted->getUpperBound()->getVersion()
    );
  }

  /**
   * Normalizes npm-specific version constraints for composer/semver.
   *
   * @param string $constraint
   *   The npm version constraint.
   *
   * @return string
   *   A normalized constraint compatible with composer/semver.
   */
  private function normalizeNpmConstraint(string $constraint): string {
    // Handle 'latest' or empty constraints.
    if ($constraint === '' || $constraint === 'latest' || $constraint === '*') {
      return '*';
    }

    // Handle 'x' ranges (e.g., '1.x', '1.2.x').
    $constraint = preg_replace('/\.x(?:\.x)?$/', '.*', $constraint);

    // Handle npm's double pipe (||) - composer uses single pipe.
    $constraint = str_replace('||', '|', $constraint);

    return $constraint;
  }

  /**
   * Resolves using first-wins strategy.
   *
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources.
   *
   * @return string
   *   The first constraint encountered.
   */
  private function resolveFirstWins(array $sources): string {
    return $sources[0]->versionConstraint;
  }

  /**
   * Resolves using fail strategy - errors on any conflict.
   *
   * @param string $packageName
   *   The npm package name.
   * @param \Drupal\Composer\NpmDependencies\DependencySource[] $sources
   *   All sources.
   * @param \Drupal\Composer\NpmDependencies\MergeResult $result
   *   The result object.
   *
   * @return null
   *   Always returns NULL since this strategy fails on conflicts.
   */
  private function resolveFail(string $packageName, array $sources, MergeResult $result): null {
    $sourceDescriptions = array_map(fn($s) => $s->getDescription(), $sources);
    $result->addError(sprintf(
      'Conflicting versions for "%s" with fail strategy: [%s]',
      $packageName,
      implode(', ', $sourceDescriptions)
    ));
    return NULL;
  }

  /**
   * Resets the merger state for reuse.
   */
  public function reset(): void {
    $this->collectedDependencies = [];
    $this->collectedDevDependencies = [];
  }

}
