<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base class for test commands.
 *
 * Provides shared configuration options for test-related commands.
 * Helps maintain consistency across test:list, test:run, and test:cleanup commands.
 *
 * @internal
 */
abstract class TestCommandBase extends Command {

  /**
   * Exit codes.
   */
  protected const EXIT_SUCCESS = 0;
  protected const EXIT_FAILURE = 1;
  protected const EXIT_ERROR = 2;
  protected const EXIT_EXCEPTION = 3;

  /**
   * The Drupal app root.
   */
  protected string $appRoot;

  /**
   * Constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->appRoot = \Drupal::root();
  }

  /**
   * Add common test filtering options to a command.
   *
   * These options are shared between test:list, test:run, and other test commands
   * to filter tests by testsuite, module, class, group, etc.
   *
   * All filtering options accept multiple values to allow flexible test selection.
   *
   * @return $this
   */
  protected function addTestFilteringOptions(): self {
    $this
      ->addOption(
        'testsuite',
        NULL,
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'PHPUnit testsuite(s) to filter by (unit, kernel, functional, functional-javascript, build, etc). Aligned with PHPUnit naming. Can be specified multiple times.'
      )
      ->addOption(
        'module',
        'm',
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'Filter by specific module(s). Can be specified multiple times.'
      )
      ->addOption(
        'directory',
        'd',
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'Filter by specific director(ies). Can be specified multiple times.'
      )
      ->addOption(
        'group',
        'g',
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'Filter by test group(s). Can be specified multiple times.'
      )
      ->addOption(
        'class',
        NULL,
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'Filter by specific test class(es). Can be specified multiple times.'
      );

    return $this;
  }

  /**
   * Add database and environment options to a command.
   *
   * These options are for commands that need to set up or interact with
   * test databases and environments.
   *
   * @return $this
   */
  protected function addDatabaseOptions(): self {
    $this
      ->addOption(
        'sqlite',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Path to SQLite database for test results.'
      )
      ->addOption(
        'dburl',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Database URL for tests.'
      )
      ->addOption(
        'url',
        'u',
        InputOption::VALUE_REQUIRED,
        'Base URL for the test site.'
      );

    return $this;
  }

  /**
   * Add test execution options to a command.
   *
   * These options are for commands that execute or manage test execution.
   *
   * @return $this
   */
  protected function addTestExecutionOptions(): self {
    $this
      ->addOption(
        'keep-results',
        NULL,
        InputOption::VALUE_NONE,
        'Keep test results in the database.'
      )
      ->addOption(
        'keep-results-table',
        NULL,
        InputOption::VALUE_NONE,
        'Keep the test results table.'
      )
      ->addOption(
        'phpunit-configuration',
        NULL,
        InputOption::VALUE_REQUIRED,
        'Path to PHPUnit configuration file.'
      )
      ->addOption(
        'verbose',
        'v',
        InputOption::VALUE_NONE,
        'Display detailed output.'
      )
      ->addOption(
        'colors',
        NULL,
        InputOption::VALUE_NEGATABLE,
        'Use colored output.',
        TRUE
      );

    return $this;
  }

  /**
   * Get PHPUnit configuration path.
   *
   * @return string
   *   The path to the PHPUnit configuration file or directory.
   */
  protected function getPhpunitConfigurationPath(): string {
    return $this->appRoot . DIRECTORY_SEPARATOR . 'core';
  }

}
