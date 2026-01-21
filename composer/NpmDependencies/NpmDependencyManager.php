<?php

namespace Drupal\Composer\NpmDependencies;

use Composer\Composer;
use Composer\IO\IOInterface;
use PhpNpm\Arborist\Arborist;

/**
 * Main orchestrator for npm dependency aggregation and installation.
 *
 * This class coordinates the entire process:
 * 1. Find all package.json files in configured paths
 * 2. Parse and collect dependencies
 * 3. Merge with conflict resolution
 * 4. Write merged package.json
 * 5. Install via php-npm Arborist
 *
 * @internal
 */
final class NpmDependencyManager {

  /**
   * The npm install options.
   */
  private readonly NpmInstallOptions $options;

  /**
   * The project root directory.
   */
  private readonly string $projectRoot;

  /**
   * Constructs an NpmDependencyManager.
   *
   * @param \Composer\Composer $composer
   *   The Composer instance.
   * @param \Composer\IO\IOInterface $io
   *   The IO interface for output.
   */
  public function __construct(
    private readonly Composer $composer,
    private readonly IOInterface $io,
  ) {
    $this->options = NpmInstallOptions::create($composer->getPackage()->getExtra());
    $this->projectRoot = dirname($composer->getConfig()->getConfigSource()->getName());
  }

  /**
   * Executes the npm dependency aggregation and installation.
   *
   * @return int
   *   Exit code: 0 for success, 1 for failure.
   */
  public function install(): int {
    $this->io->write('<info>Drupal npm dependency aggregation</info>');
    $this->io->write('');

    // Step 1: Find package.json files.
    $this->io->write('Scanning for package.json files...');
    $finder = new PackageJsonFinder($this->projectRoot, $this->options);
    $packageJsonFiles = $finder->find();

    $fileCount = iterator_count($packageJsonFiles);
    if ($fileCount === 0) {
      $this->io->write('<comment>No package.json files found in configured paths.</comment>');
      $this->io->write('Configured paths: ' . implode(', ', $this->options->scanPaths));
      return 0;
    }

    $this->io->write(sprintf('Found <info>%d</info> package.json file(s)', $fileCount));
    $this->io->write('');

    // Step 2: Parse and collect dependencies.
    $this->io->write('Parsing dependencies...');
    $merger = new PackageJsonMerger($this->options);

    // Re-create finder since we consumed it counting.
    $packageJsonFiles = $finder->find();
    foreach ($packageJsonFiles as $file) {
      try {
        $parsed = $finder->parsePackageJson($file);
        $merger->addDependencies($parsed['dependencies'], $parsed['devDependencies']);

        if ($this->io->isVerbose()) {
          $depCount = count($parsed['dependencies']);
          $devCount = count($parsed['devDependencies']);
          $this->io->write(sprintf(
            '  - %s: %d dependencies, %d devDependencies',
            $file->getRelativePathname(),
            $depCount,
            $devCount
          ));
        }
      }
      catch (\RuntimeException $e) {
        $this->io->writeError(sprintf('<error>Error parsing %s: %s</error>', $file->getPathname(), $e->getMessage()));
      }
    }

    // Step 3: Merge with conflict resolution.
    $this->io->write('');
    $this->io->write(sprintf('Merging dependencies (strategy: %s)...', $this->options->conflictResolution));
    $result = $merger->merge();

    // Report warnings.
    foreach ($result->getWarnings() as $warning) {
      $this->io->write(sprintf('<comment>  Warning: %s</comment>', $warning));
    }

    // Check for errors.
    if (!$result->isSuccessful()) {
      $this->io->writeError('');
      $this->io->writeError('<error>Dependency merge failed with errors:</error>');
      foreach ($result->getErrors() as $error) {
        $this->io->writeError(sprintf('  - %s', $error));
      }
      return 1;
    }

    $this->io->write(sprintf(
      'Merged <info>%d</info> total dependencies (%d conflicts resolved)',
      $result->getTotalDependencyCount(),
      count($result->getResolvedConflicts())
    ));

    // Step 4: Write merged package.json.
    $this->io->write('');
    $outputPath = $this->getOutputPath();
    $this->io->write(sprintf('Writing package.json to %s...', $outputPath));

    try {
      $this->writePackageJson($result, $outputPath);
    }
    catch (\RuntimeException $e) {
      $this->io->writeError(sprintf('<error>Failed to write package.json: %s</error>', $e->getMessage()));
      return 1;
    }

    // Step 5: Install via php-npm.
    $this->io->write('');
    $this->io->write('Installing npm packages...');

    try {
      $this->installPackages(dirname($outputPath));
      $this->io->write('<info>npm packages installed successfully.</info>');
    }
    catch (\Throwable $e) {
      $this->io->writeError(sprintf('<error>npm install failed: %s</error>', $e->getMessage()));
      return 1;
    }

    $this->io->write('');
    $this->io->write('<info>Done!</info>');

    return 0;
  }

  /**
   * Gets the full path for the output package.json.
   *
   * @return string
   *   The absolute path.
   */
  private function getOutputPath(): string {
    $outputDir = $this->options->outputPath;
    if (!str_starts_with($outputDir, DIRECTORY_SEPARATOR)) {
      $outputDir = $this->projectRoot . DIRECTORY_SEPARATOR . $outputDir;
    }
    return rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'package.json';
  }

  /**
   * Writes the merged package.json file.
   *
   * @param \Drupal\Composer\NpmDependencies\MergeResult $result
   *   The merge result.
   * @param string $outputPath
   *   The path to write to.
   *
   * @throws \RuntimeException
   *   If writing fails.
   */
  private function writePackageJson(MergeResult $result, string $outputPath): void {
    $packageJson = [];

    // Preserve existing package.json if configured.
    if ($this->options->preserveExisting && file_exists($outputPath)) {
      $existing = file_get_contents($outputPath);
      if ($existing !== FALSE) {
        $decoded = json_decode($existing, TRUE);
        if (is_array($decoded)) {
          $packageJson = $decoded;
        }
      }
    }

    // Set basic metadata if not preserving or no existing file.
    if (!isset($packageJson['name'])) {
      $packageJson['name'] = 'drupal-project';
    }
    if (!isset($packageJson['private'])) {
      $packageJson['private'] = TRUE;
    }
    if (!isset($packageJson['description'])) {
      $packageJson['description'] = 'Aggregated npm dependencies from Drupal modules and themes';
    }

    // Merge dependencies.
    $dependencies = $result->getDependencies();
    if (!empty($dependencies)) {
      $packageJson['dependencies'] = array_merge(
        $packageJson['dependencies'] ?? [],
        $dependencies
      );
      ksort($packageJson['dependencies']);
    }

    // Merge devDependencies.
    $devDependencies = $result->getDevDependencies();
    if (!empty($devDependencies)) {
      $packageJson['devDependencies'] = array_merge(
        $packageJson['devDependencies'] ?? [],
        $devDependencies
      );
      ksort($packageJson['devDependencies']);
    }

    // Add metadata about sources.
    $packageJson['_drupalSources'] = $result->generateSourcesMetadata();
    $packageJson['_generatedBy'] = 'drupal/drupal composer npm-install';
    $packageJson['_generatedAt'] = date('c');

    // Write the file.
    $json = json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === FALSE) {
      throw new \RuntimeException('Failed to encode package.json');
    }

    $dir = dirname($outputPath);
    if (!is_dir($dir) && !mkdir($dir, 0755, TRUE)) {
      throw new \RuntimeException(sprintf('Failed to create directory: %s', $dir));
    }

    if (file_put_contents($outputPath, $json . "\n") === FALSE) {
      throw new \RuntimeException(sprintf('Failed to write file: %s', $outputPath));
    }
  }

  /**
   * Installs npm packages using php-npm Arborist.
   *
   * @param string $projectPath
   *   The directory containing package.json.
   *
   * @throws \RuntimeException
   *   If the php-npm library is not available.
   */
  private function installPackages(string $projectPath): void {
    if (!class_exists(Arborist::class)) {
      throw new \RuntimeException(
        'The theodoreb/php-npm package is required. Run: composer require theodoreb/php-npm'
      );
    }

    $arborist = new Arborist($projectPath);
    $arborist->install();
  }

}
