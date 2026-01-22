<?php

namespace Drupal\Composer\NpmDependencies;

/**
 * Per-project options from the 'extras' section of the composer.json file.
 *
 * Projects that use the npm dependency aggregation can configure it via the
 * 'drupal-npm-install' portion of the extras section.
 *
 * @code
 *  "extras": {
 *    "drupal-npm-install": {
 *      "scan-paths": ["modules/contrib", "modules/custom"],
 *      "include-dev-dependencies": false,
 *      "conflict-resolution": "highest-satisfies"
 *    }
 *  }
 * @endcode
 *
 * @internal
 */
final readonly class NpmInstallOptions {

  /**
   * The ID of the extra section in the top-level composer.json file.
   */
  const string ID = 'drupal-npm-install';

  /**
   * Default paths to scan for package.json files.
   *
   * Core is listed first to ensure its dependencies take precedence
   * when using first-wins conflict resolution strategy.
   */
  const array DEFAULT_SCAN_PATHS = [
    'core',
    'modules',
    'themes',
    'profiles',
  ];

  /**
   * Valid conflict resolution strategies.
   */
  const array VALID_STRATEGIES = [
    'highest-satisfies',
    'first-wins',
    'fail',
  ];

  /**
   * The paths to scan for package.json files.
   *
   * @var string[]
   */
  public array $scanPaths;

  /**
   * Whether to include devDependencies from discovered package.json files.
   */
  public bool $includeDevDependencies;

  /**
   * The conflict resolution strategy to use.
   */
  public string $conflictResolution;

  /**
   * Module paths to ignore when scanning.
   *
   * @var string[]
   */
  public array $ignorePackages;

  /**
   * The path where the merged package.json should be written.
   */
  public string $outputPath;

  /**
   * Whether to preserve and merge with an existing package.json at outputPath.
   */
  public bool $preserveExisting;

  /**
   * Whether to show verbose conflict resolution details.
   */
  public bool $verboseConflicts;

  private function __construct(array $options) {
    $this->scanPaths = $options['scan-paths'] ?? self::DEFAULT_SCAN_PATHS;
    $this->includeDevDependencies = $options['include-dev-dependencies'] ?? FALSE;
    $this->conflictResolution = $options['conflict-resolution'] ?? 'highest-satisfies';
    $this->ignorePackages = $options['ignore-packages'] ?? [];
    $this->outputPath = $options['output-path'] ?? '.';
    $this->preserveExisting = $options['preserve-existing'] ?? FALSE;
    $this->verboseConflicts = $options['verbose-conflicts'] ?? TRUE;

    if (!in_array($this->conflictResolution, self::VALID_STRATEGIES, TRUE)) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid conflict resolution strategy "%s". Valid strategies are: %s',
        $this->conflictResolution,
        implode(', ', self::VALID_STRATEGIES)
      ));
    }
  }

  /**
   * Creates an NpmInstallOptions object from composer extras.
   *
   * @param array $extras
   *   The contents of the 'extras' section from composer.json.
   *
   * @return self
   *   The options object.
   */
  public static function create(array $extras): self {
    $options = $extras[self::ID] ?? [];
    return new self($options);
  }

  /**
   * Checks if a given path should be ignored.
   *
   * @param string $path
   *   The path to check (relative to project root).
   *
   * @return bool
   *   TRUE if the path should be ignored.
   */
  public function isIgnored(string $path): bool {
    foreach ($this->ignorePackages as $ignoredPath) {
      if (str_starts_with($path, $ignoredPath) || $path === $ignoredPath) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
