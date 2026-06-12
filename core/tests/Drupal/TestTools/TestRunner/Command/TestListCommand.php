<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Command;

use Drupal\Core\Test\PhpUnitTestDiscovery;
use Drupal\TestTools\TestRunner\PopulateClassLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command for listing Drupal tests.
 *
 * This command lists all available PHPUnit tests organized by group.
 * It replaces the legacy run-tests.sh --list, --list-files and --list-files-json
 * functionality.
 *
 * @internal
 */
#[AsCommand(
  name: 'test:list',
  description: 'List available tests',
)]
class TestListCommand extends TestCommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    parent::configure();
    $this
      ->addTestFilteringOptions()
      ->addOption(
        'files',
        'l',
        InputOption::VALUE_NONE,
        'List test files instead of test classes.'
      )
      ->addOption(
        'json',
        NULL,
        InputOption::VALUE_NONE,
        'Output as JSON.'
      )
      ->setHelp(
        'Lists available tests organized by group or as a file list.' . "\n\n" .
        'Examples:' . "\n" .
        '  <info>dr test:list</info>' . "\n" .
        '  <info>dr test:list --testsuite unit</info>' . "\n" .
        '  <info>dr test:list --module node</info>' . "\n" .
        '  <info>dr test:list --directory core/modules/system/tests</info>' . "\n" .
        '  <info>dr test:list --group Block</info>' . "\n" .
        '  <info>dr test:list --class Drupal\\\\Tests\\\\block\\\\Functional\\\\BlockTest</info>' . "\n" .
        '  <info>dr test:list --files</info>' . "\n" .
        '  <info>dr test:list --files --json</info>'
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    // Check PHPUnit is available.
    if (!class_exists(TestCase::class)) {
      $io->error('PHPUnit testing framework is required. Run "composer install" to ensure it is present.');
      return self::EXIT_FAILURE;
    }

    PopulateClassLoader::execute($this->classLoader, $this->appRoot);

    $testDiscovery = PhpUnitTestDiscovery::instance()
      ->setConfigurationFilePath($this->getPhpunitConfigurationPath());

    try {
      $module = $this->getFirstOrNull($input->getOption('module'));
      $testsuites = $input->getOption('testsuite') ?: [];
      $directory = $this->getFirstOrNull($input->getOption('directory'));
      $groups = $input->getOption('group') ?: [];
      $classes = $input->getOption('class') ?: [];

      // Determine which filter to apply.
      $filters = [];
      if (!empty($classes)) {
        $filters = $classes;
      } elseif (!empty($groups)) {
        $filters = $groups;
      }

      if ($input->getOption('files')) {
        return $this->listFiles($testDiscovery, $module, $testsuites, $directory, $filters, $input, $io);
      }

      return $this->listClasses($testDiscovery, $module, $testsuites, $directory, $filters, $io);
    } catch (\Exception $e) {
      $io->error((string) $e);
      return self::EXIT_EXCEPTION;
    }
  }

  /**
   * List test classes organized by group.
   */
  protected function listClasses(
    PhpUnitTestDiscovery $testDiscovery,
    ?string $module,
    array $testsuites,
    ?string $directory,
    array $filters,
    SymfonyStyle $io
  ): int {
    $io->section('Available test groups & classes');

    $groupedTestClassInfoList = $testDiscovery->getTestClasses(
      $module,
      $testsuites,
      $directory,
      $filters
    );

    if (empty($groupedTestClassInfoList)) {
      $io->warning('No tests found matching the specified filters.');
      return self::EXIT_SUCCESS;
    }

    // Display warnings from test discovery.
    $this->displayDiscoveryWarnings($testDiscovery, $io);

    // A given class can appear in multiple groups. For historical reasons, we
    // need to present each test only once. The test is shown in the group that is
    // printed first.
    $printed_tests = [];
    foreach ($groupedTestClassInfoList as $group => $tests) {
      $io->writeln('<fg=cyan>' . $group . '</>');
      $tests = array_diff(array_keys($tests), $printed_tests);
      foreach ($tests as $test) {
        $io->writeln(' - ' . $test);
      }
      $printed_tests = array_merge($printed_tests, $tests);
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * List test files.
   */
  protected function listFiles(
    PhpUnitTestDiscovery $testDiscovery,
    ?string $module,
    array $testsuites,
    ?string $directory,
    array $filters,
    InputInterface $input,
    SymfonyStyle $io
  ): int {
    $testClasses = $testDiscovery->findAllClassFiles(
      $module,
      $testsuites,
      $directory,
      $filters
    );

    if (empty($testClasses)) {
      $io->warning('No test files found matching the specified filters.');
      return self::EXIT_SUCCESS;
    }

    if ($input->getOption('json')) {
      $io->write(json_encode(array_values($testClasses)));
    } else {
      foreach (array_values($testClasses) as $test_file) {
        $io->writeln($test_file);
      }
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Display test discovery warnings.
   */
  protected function displayDiscoveryWarnings(PhpUnitTestDiscovery $testDiscovery, SymfonyStyle $io): void {
    $warnings = $testDiscovery->getWarnings();
    if (!empty($warnings)) {
      $io->caution('Test discovery warnings');
      foreach ($warnings as $warning) {
        $tmp = explode("\n", $warning);
        $io->writeln('* ' . array_shift($tmp));
        foreach ($tmp as $sub) {
          $io->writeln('  ' . $sub);
        }
      }
      $io->writeln('');
    }
  }

  /**
   * Get first element from an array or null.
   *
   * Helper to convert array options to single values when only one is provided.
   * Used for options that logically accept a single value but are defined as arrays
   * to support Symfony's option parsing.
   *
   * @param array $array
   *   The array to get the first element from.
   *
   * @return mixed|null
   *   The first element of the array or null if empty.
   */
  protected function getFirstOrNull(array $array) {
    return empty($array) ? NULL : reset($array);
  }

}
