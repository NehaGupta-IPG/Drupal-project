<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Helper class to manage run-tests.sh configuration.
 */
final class Configuration {

  protected static ?self $instance = NULL;

  protected function __construct(
    private array $options,
    private array $arguments,
  ) {
  }

  /**
   * Gets a config element.
   */
  public function get(string $name): mixed {
    if (array_key_exists($name, $this->options)) {
      return $this->options[$name];
    }
    throw new \RuntimeException("There is no '{$name}' element in config");
  }

  /**
   * Sets a config element.
   */
  public function set(string $name, mixed $value): void {
    $this->options[$name] = $value;
  }

  public static function createFromCommandLine(): self {
    if (self::$instance) {
      return(self::$instance);
    }

    try {
      $input = new ArgvInput(NULL, self::commandLineDefinition());
    }
    catch (ConsoleRuntimeException $e) {
      simpletest_script_print_error(NULL, $e->getMessage() . ' ' . "Use the --help option for the list and usage of the options available.\n");
      simpletest_script_print(NULL, self::commandLineDefinition()->getSynopsis(), SIMPLETEST_SCRIPT_COLOR_PASS);
      exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
    }

    // Validate the concurrency argument.
    $concurrency = $input->getOption('concurrency');
    if (!is_numeric($concurrency) || $concurrency <= 0) {
      simpletest_script_print_error($input, "--concurrency must be a strictly positive integer.");
      exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
    }

    if ($input->getArgument('tests')) {
      $input->setArgument('tests', explode(',', $input->getArgument('tests')));
    }
    else {
      $input->setArgument('tests', []);
    }

    if ($input->getOption('types')) {
      $input->setOption('types', explode(',', $input->getOption('types')));
    }
    else {
      $input->setOption('types', []);
    }

    if ($input->getOption('ci-parallel-node-index') === NULL) {
      $input->setOption('ci-parallel-node-index', 1);
    }

    if ($input->getOption('ci-parallel-node-total') === NULL) {
      $input->setOption('ci-parallel-node-total', 1);
    }

    self::$instance = new self($input->getOptions(), $input->getArguments());
    return self::$instance;
  }

  /**
   * Describes the command line arguments and options.
   *
   * @return \Symfony\Component\Console\Input\InputDefinition
   *   The command line input definition.
   */
  public static function commandLineDefinition(): InputDefinition {
    $inputDefinition = new InputDefinition();
    $inputDefinition->addOption(new InputOption(
      'help',
      NULL,
      InputOption::VALUE_NONE,
      'Print this page.',
    ));
    $inputDefinition->addOption(new InputOption(
      'phpunit-configuration',
      NULL,
      InputOption::VALUE_REQUIRED,
      'Path to the configuration file for PHPUnit. If not specified, it defaults to core configuration.',
    ));
    $inputDefinition->addOption(new InputOption(
      'list',
      NULL,
      InputOption::VALUE_NONE,
      'Display all available test groups.',
    ));
    $inputDefinition->addOption(new InputOption(
      'list-files',
      NULL,
      InputOption::VALUE_NONE,
      'Display all discoverable test file paths.',
    ));
    $inputDefinition->addOption(new InputOption(
      'list-files-json',
      NULL,
      InputOption::VALUE_NONE,
      "Display all discoverable test files as JSON.\nThe array key will be the test class name, and the value will be the file path of the test.",
    ));
    $inputDefinition->addOption(new InputOption(
      'clean',
      NULL,
      InputOption::VALUE_NONE,
      'Cleans up database tables or directories from previous, failed, tests and then exits (no tests are run).',
    ));
    $inputDefinition->addOption(new InputOption(
      'url',
      NULL,
      InputOption::VALUE_REQUIRED,
      "The base URL of the root directory of this Drupal checkout; e.g.: http://drupal.test/.\nRequired unless the Drupal root directory maps exactly to: http://localhost:80/.\nUse a https:// URL to force all tests to be run under SSL.",
    ));
    $inputDefinition->addOption(new InputOption(
      'sqlite',
      NULL,
      InputOption::VALUE_REQUIRED,
      'A pathname to use for the SQLite database of the test runner. Required unless this script is executed with a working Drupal installation. A relative pathname is interpreted relative to the Drupal root directory. Note that \':memory:\' cannot be used, because this script spawns sub-processes. However, you may use e.g. \'/tmpfs/test.sqlite\'',
    ));
    $inputDefinition->addOption(new InputOption(
      'dburl',
      NULL,
      InputOption::VALUE_REQUIRED,
      "A URI denoting the database driver, credentials, server hostname, and database name to use in tests.\nRequired when running tests without a Drupal installation that contains default database connection info in settings.php.\nExamples:\nmysql://username:password@localhost/database_name#table_prefix\nsqlite://localhost/relative/path/db.sqlite\nsqlite://localhost//absolute/path/db.sqlite",
    ));
    $inputDefinition->addOption(new InputOption(
      'php',
      NULL,
      InputOption::VALUE_REQUIRED,
      "The absolute path to the PHP executable. Usually not needed.",
    ));
    $inputDefinition->addOption(new InputOption(
      'concurrency',
      NULL,
      InputOption::VALUE_REQUIRED,
      "Run tests in parallel, up to [num] tests at a time.",
      1,
    ));
    $inputDefinition->addOption(new InputOption(
      'all',
      NULL,
      InputOption::VALUE_NONE,
      'Run all available tests.',
    ));
    $inputDefinition->addOption(new InputOption(
      'module',
      NULL,
      InputOption::VALUE_REQUIRED,
      "Run all tests belonging to the specified module name. (e.g., 'node')",
    ));
    $inputDefinition->addOption(new InputOption(
      'class',
      NULL,
      InputOption::VALUE_NONE,
      'Run tests identified by specific class names, instead of group names.',
    ));
    $inputDefinition->addOption(new InputOption(
      'file',
      NULL,
      InputOption::VALUE_NONE,
      "Run tests identified by specific file names, instead of group names.\nSpecify the path and the extension (i.e. 'core/modules/user/tests/src/Functional/UserCreateTest.php').",
    ));
    $inputDefinition->addOption(new InputOption(
      'types',
      NULL,
      InputOption::VALUE_REQUIRED,
      'Runs just tests from the specified test type, for example run-tests.sh --types "PHPUnit-Unit,PHPUnit-Kernel"',
    ));
    $inputDefinition->addOption(new InputOption(
      'directory',
      NULL,
      InputOption::VALUE_REQUIRED,
      'Run all tests found within the specified file directory.',
    ));
    $inputDefinition->addOption(new InputOption(
      'color',
      NULL,
      InputOption::VALUE_NONE,
      'Output text format results with color highlighting.',
    ));
    $inputDefinition->addOption(new InputOption(
      'verbose',
      NULL,
      InputOption::VALUE_NONE,
      'Output detailed assertion messages in addition to summary.',
    ));
    $inputDefinition->addOption(new InputOption(
      'keep-results',
      NULL,
      InputOption::VALUE_NONE,
      "Keeps detailed assertion results (in the database) after tests have completed. By default, assertion results are cleared.",
    ));
    $inputDefinition->addOption(new InputOption(
      'keep-results-table',
      NULL,
      InputOption::VALUE_NONE,
      "Boolean flag to indicate to not cleanup the simpletest result table. For testbots or repeated execution of a single test it can be helpful to not cleanup the simpletest result table.",
    ));
    $inputDefinition->addOption(new InputOption(
      'repeat',
      NULL,
      InputOption::VALUE_REQUIRED,
      "Number of times to repeat the test.",
      1,
    ));
    $inputDefinition->addOption(new InputOption(
      'die-on-fail',
      NULL,
      InputOption::VALUE_NONE,
      "Exit test execution immediately upon any failed assertion.\nThis allows to access the test site by changing settings.php to use the test database and configuration directories.\nUse in combination with --repeat for debugging random test failures.",
    ));
    $inputDefinition->addOption(new InputOption(
      'suppress-deprecations',
      NULL,
      InputOption::VALUE_NONE,
      "Stops tests from failing if deprecation errors are triggered.\nIf this is not set the value specified in the SYMFONY_DEPRECATIONS_HELPER environment variable, or the value specified in core/phpunit.xml (if it exists), or the default value will be used.\nThe default is that any unexpected silenced deprecation error will fail tests.",
    ));
    $inputDefinition->addOption(new InputOption(
      'test-id',
      NULL,
      InputOption::VALUE_REQUIRED,
      "(internal)",
    ));
    $inputDefinition->addOption(new InputOption(
      'execute-test',
      NULL,
      InputOption::VALUE_REQUIRED,
      "(internal)",
    ));
    $inputDefinition->addOption(new InputOption(
      'xml',
      NULL,
      InputOption::VALUE_REQUIRED,
      "If provided, test results will be written as xml files to this path.",
    ));
    $inputDefinition->addOption(new InputOption(
      'non-html',
      NULL,
      InputOption::VALUE_NONE,
      'Removes escaping from output. Useful for reading results on the CLI.',
    ));
    $inputDefinition->addOption(new InputOption(
      'ci-parallel-node-index',
      NULL,
      InputOption::VALUE_OPTIONAL,
      'The index of the job in the job set.',
    ));
    $inputDefinition->addOption(new InputOption(
      'ci-parallel-node-total',
      NULL,
      InputOption::VALUE_OPTIONAL,
      'The total number of instances of this job running in parallel.',
    ));
    $inputDefinition->addOption(new InputOption(
      'debug-discovery',
      NULL,
      InputOption::VALUE_NONE,
      'If provided, dumps detailed information on the tests selected for execution, before the execution starts.'
    ));

    $inputDefinition->addArgument(new InputArgument(
      'tests',
      InputArgument::OPTIONAL,
      "One or more tests to be run.\nBy default, these are interpreted as the names of test groups which are derived from test class @group annotations. These group names typically correspond to module names like \"User\" or \"Profile\" or \"System\", but there is also a group \"Database\".\nIf --class is specified then these are interpreted as the names of specific test classes whose test methods will be run.\nTests must be separated by commas.\nIgnored if --all is specified.",
    ));

    return $inputDefinition;
  }

}
