<?php

/**
 * @file
 * Script for running tests on DrupalCI.
 *
 * This script is intended for use only by drupal.org's testing. In general,
 * tests should be run directly with phpunit.
 *
 * @internal
 */

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Timer;
use Drupal\Core\Composer\Composer;
use Drupal\Core\Database\Database;
use Drupal\Core\Test\EnvironmentCleaner;
use Drupal\Core\Test\PhpUnitTestDiscovery;
use Drupal\Core\Test\PhpUnitTestRunner;
use Drupal\Core\Test\SimpletestTestRunResultsStorage;
use Drupal\Core\Test\TestDatabase;
use Drupal\Core\Test\TestRun;
use Drupal\Core\Test\TestRunnerKernel;
use Drupal\Core\Test\TestRunResultsStorageInterface;
use Drupal\BuildTests\Framework\BuildTestBase;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\BrowserTestBase;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version;
use Symfony\Component\Console\Exception\RuntimeException as ConsoleRuntimeException;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputAwareInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;

// cspell:ignore exitcode testbots wwwrun

// Define some colors for display.
// A nice calming green.
const SIMPLETEST_SCRIPT_COLOR_PASS = 32;
// An alerting Red.
const SIMPLETEST_SCRIPT_COLOR_FAIL = 31;
// An annoying brown.
const SIMPLETEST_SCRIPT_COLOR_EXCEPTION = 33;
// An appeasing yellow.
const SIMPLETEST_SCRIPT_COLOR_YELLOW = 33;
// A refreshing cyan.
const SIMPLETEST_SCRIPT_COLOR_CYAN = 36;
// A fainting gray.
const SIMPLETEST_SCRIPT_COLOR_GRAY = 90;
// A notable white.
const SIMPLETEST_SCRIPT_COLOR_BRIGHT_WHITE = "1;97";

// Restricting the chunk of queries prevents memory exhaustion.
const SIMPLETEST_SCRIPT_SQLITE_VARIABLE_LIMIT = 350;

const SIMPLETEST_SCRIPT_EXIT_SUCCESS = 0;
const SIMPLETEST_SCRIPT_EXIT_FAILURE = 1;
const SIMPLETEST_SCRIPT_EXIT_ERROR = 2;
const SIMPLETEST_SCRIPT_EXIT_EXCEPTION = 3;

// Setup class autoloading.
$autoloader = require_once __DIR__ . '/../../autoload.php';
// The PHPUnit compatibility layer needs to be available to autoload tests.
$autoloader->add('Drupal\\TestTools', __DIR__ . '/../tests');

// Get the options from the command line.
/** @var \Symfony\Component\Console\Input\InputDefinition $command_line_definition */
$command_line_definition = command_line_definition();
$script_basename = basename($_SERVER['argv'][0]);
/** @var \Symfony\Component\Console\Input\InputInterface $input */
$input = parse_command_line($command_line_definition);

// Setup console output.
$console_output = new ConsoleOutput();

// If --help requested, show it and exit.
if ($input->getOption('help')) {
  simpletest_script_help($command_line_definition, $script_basename, $console_output);
  exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
}

simpletest_script_init($input, $autoloader);

if (!class_exists(TestCase::class)) {
  echo "\nrun-tests.sh requires the PHPUnit testing framework. Use 'composer install' to ensure that it is present.\n\n";
  exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
}

if ($input->getOption('execute-test')) {
  simpletest_script_setup_database($input);
  $test_run_results_storage = simpletest_script_setup_test_run_results_storage($input);
  $test_run = TestRun::get($test_run_results_storage, $input->getOption('test-id'));
  simpletest_script_run_one_test($input, $test_run, $input->getOption('execute-test'));
  // Sub-process exited already; this is just for clarity.
  exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
}

if ($input->getOption('list')) {
  // Display all available tests organized by one @group annotation.
  echo "\nAvailable test groups & classes\n";
  echo "-------------------------------\n\n";
  $test_discovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath(\Drupal::root() . \DIRECTORY_SEPARATOR . 'core');
  try {
    $groups = $test_discovery->getTestClasses($input->getOption('module'));
    foreach ($test_discovery->getWarnings() as $warning) {
      simpletest_script_print($input, $warning . "\n", SIMPLETEST_SCRIPT_COLOR_EXCEPTION);
    }
  }
  catch (Exception $e) {
    error_log((string) $e);
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }

  // A given class can appear in multiple groups. For historical reasons, we
  // need to present each test only once. The test is shown in the group that is
  // printed first.
  $printed_tests = [];
  foreach ($groups as $group => $tests) {
    echo $group . "\n";
    $tests = array_diff(array_keys($tests), $printed_tests);
    foreach ($tests as $test) {
      echo " - $test\n";
    }
    $printed_tests = array_merge($printed_tests, $tests);
  }
  exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
}

// List-files and list-files-json provide a way for external tools such as the
// testbot to prioritize running changed tests.
// @see https://www.drupal.org/node/2569585
if ($input->getOption('list-files') || $input->getOption('list-files-json')) {
  // List all files which could be run as tests.
  $test_discovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath(\Drupal::root() . \DIRECTORY_SEPARATOR . 'core');
  // PhpUnitTestDiscovery::findAllClassFiles() gives us a classmap similar to a
  // Composer 'classmap' array.
  $test_classes = $test_discovery->findAllClassFiles();
  // JSON output is the easiest.
  if ($input->getOption('list-files-json')) {
    echo json_encode($test_classes);
    exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
  }
  // Output the list of files.
  else {
    foreach (array_values($test_classes) as $test_class) {
      echo $test_class . "\n";
    }
  }
  exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
}

simpletest_script_setup_database($input, TRUE);

// Setup the test run results storage environment. Currently, this coincides
// with the simpletest database schema.
$test_run_results_storage = simpletest_script_setup_test_run_results_storage($input, TRUE);

if ($input->getOption('clean')) {
  // Clean up left-over tables and directories.
  $cleaner = new EnvironmentCleaner(
    DRUPAL_ROOT,
    Database::getConnection(),
    $test_run_results_storage,
    $console_output,
    \Drupal::service('file_system')
  );
  try {
    $cleaner->cleanEnvironment();
  }
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }
  echo "\nEnvironment cleaned.\n";

  // Get the status messages and print them.
  $messages = \Drupal::messenger()->messagesByType('status');
  foreach ($messages as $text) {
    echo " - " . $text . "\n";
  }
  exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
}

if (!Composer::upgradePHPUnitCheck(Version::id())) {
  simpletest_script_print_error($input, "PHPUnit testing framework version 11 or greater is required when running on PHP 8.4 or greater. Run the command 'composer run-script drupal-phpunit-upgrade' in order to fix this.");
  exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
}

echo "\n";
echo "Drupal test run\n\n";
echo sprintf("Drupal Version:   %s\n", \Drupal::VERSION);
echo sprintf("PHP Version:      %s\n", \PHP_VERSION);
echo sprintf("PHP Binary:       %s\n", $php ?? getenv('_'));
echo sprintf("PHPUnit Version:  %s\n", Version::id());
if ($input->getOption('dburl')) {
  $sut_connection_info = Database::getConnectionInfo();
  $sut_tasks_class = $sut_connection_info['default']['namespace'] . "\\Install\\Tasks";
  $sut_installer = new $sut_tasks_class();
  $sut_connection = Database::getConnection();
  echo sprintf("Database:         %s\n", (string) $sut_installer->name());
  echo sprintf("Database Version: %s\n", $sut_connection->version());
}
echo "-------------------------------\n";
echo "\n";

$test_list = simpletest_script_get_test_list($input);

// Try to allocate unlimited time to run the tests.
Environment::setTimeLimit(0);
simpletest_script_reporter_init($input);

$tests_to_run = [];
for ($i = 0; $i < $input->getOption('repeat'); $i++) {
  $tests_to_run = array_merge($tests_to_run, $test_list);
}

// Execute tests.
$status = simpletest_script_execute_batch($input, $test_run_results_storage, $tests_to_run, $script_basename);

// Stop the timer.
simpletest_script_reporter_timer_stop();

// Ensure all test locks are released once finished. If tests are run with a
// concurrency of 1 the each test will clean up its own lock. Test locks are
// not released if using a higher concurrency to ensure each test has unique
// fixtures.
TestDatabase::releaseAllTestLocks();

// Display results before database is cleared.
simpletest_script_reporter_display_results($input, $test_run_results_storage);

if ($input->getOption('xml')) {
  simpletest_script_reporter_write_xml_results($input, $test_run_results_storage);
}

// Clean up all test results.
if (!$input->getOption('keep-results')) {
  try {
    $cleaner = new EnvironmentCleaner(
      DRUPAL_ROOT,
      Database::getConnection(),
      $test_run_results_storage,
      $console_output,
      \Drupal::service('file_system')
    );
    $cleaner->cleanResults();
  }
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }
}

// Test complete, exit.
exit($status);

/**
 * Print help text.
 */
function simpletest_script_help(InputDefinition $input_definition, string $script_basename, ConsoleOutput $console_output): void {

  echo <<<EOF

Run Drupal tests from the shell.

Usage:        {$script_basename} [OPTIONS] <tests>
Example:      {$script_basename} Profile


EOF;

  $helper = new DescriptorHelper();
  $helper->describe($console_output, $input_definition);

  echo <<<EOF


To run this script you will normally invoke it from the root directory of your
Drupal installation as the webserver user (differs per configuration), or root:

sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename}
  --url http://example.com/ --all
sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename}
  --url http://example.com/ --class Drupal\Tests\block\Functional\BlockTest

Without a preinstalled Drupal site, specify a SQLite database pathname to create
(for the test runner) and the default database connection info (for Drupal) to
use in tests:

sudo -u [wwwrun|www-data|etc] php ./core/scripts/{$script_basename}
  --sqlite /tmpfs/drupal/test.sqlite
  --dburl mysql://username:password@localhost/database
  --url http://example.com/ --all


EOF;
}

/**
 * Describes the command line arguments and options.
 *
 * @return \Symfony\Component\Console\Input\InputDefinition
 *   The command line input definition.
 */
function command_line_definition(): InputDefinition {
  $inputDefinition = new InputDefinition();
  $inputDefinition->addOption(new InputOption(
    'help',
    NULL,
    NULL,
    'Print this page.',
  ));
  $inputDefinition->addOption(new InputOption(
    'list',
    NULL,
    NULL,
    'Display all available test groups.',
  ));
  $inputDefinition->addOption(new InputOption(
    'list-files',
    NULL,
    NULL,
    'Display all discoverable test file paths.',
  ));
  $inputDefinition->addOption(new InputOption(
    'list-files-json',
    NULL,
    NULL,
    "Display all discoverable test files as JSON.\nThe array key will be the test class name, and the value will be the file path of the test.",
  ));
  $inputDefinition->addOption(new InputOption(
    'clean',
    NULL,
    NULL,
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
    NULL,
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
    NULL,
    'Run tests identified by specific class names, instead of group names.',
  ));
  $inputDefinition->addOption(new InputOption(
    'file',
    NULL,
    NULL,
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
    NULL,
    'Output text format results with color highlighting.',
  ));
  $inputDefinition->addOption(new InputOption(
    'verbose',
    NULL,
    NULL,
    'Output detailed assertion messages in addition to summary.',
  ));
  $inputDefinition->addOption(new InputOption(
    'keep-results',
    NULL,
    NULL,
    "Keeps detailed assertion results (in the database) after tests have completed. By default, assertion results are cleared.",
  ));
  $inputDefinition->addOption(new InputOption(
    'keep-results-table',
    NULL,
    NULL,
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
    NULL,
    "Exit test execution immediately upon any failed assertion.\nThis allows to access the test site by changing settings.php to use the test database and configuration directories.\nUse in combination with --repeat for debugging random test failures.",
  ));
  $inputDefinition->addOption(new InputOption(
    'suppress-deprecations',
    NULL,
    NULL,
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
    NULL,
    'Removes escaping from output. Useful for reading results on the CLI.',
  ));
  $inputDefinition->addOption(new InputOption(
    'ci-parallel-node-index',
    NULL,
    InputOption::VALUE_OPTIONAL, 'The index of the job in the job set.',
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
    NULL,
    'If provided, dumps detailed information on the tests selected for execution, before the execution starts.'
  ));

  $inputDefinition->addArgument(new InputArgument(
    'tests',
    InputArgument::OPTIONAL,
    "One or more tests to be run.\nBy default, these are interpreted as the names of test groups which are derived from test class @group annotations. These group names typically correspond to module names like \"User\" or \"Profile\" or \"System\", but there is also a group \"Database\".\nIf --class is specified then these are interpreted as the names of specific test classes whose test methods will be run.\nTests must be separated by commas.\nIgnored if --all is specified.",
  ));

  return $inputDefinition;
}

/**
 * Parse execution argument and ensure that all are valid.
 *
 * @return \Symfony\Component\Console\Input\InputInterface
 *   The input options and argument.
 */
function parse_command_line(InputDefinition $input_definition): InputInterface {
  try {
    $input = new ArgvInput(NULL, $input_definition);
  }
  catch (ConsoleRuntimeException $e) {
    simpletest_script_print_error(NULL, $e->getMessage() . ' ' . "Use the --help option for the list and usage of the options available.\n");
    simpletest_script_print(NULL, $input_definition->getSynopsis(), SIMPLETEST_SCRIPT_COLOR_PASS);
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

  return $input;
}

/**
 * Initialize script variables and perform general setup requirements.
 */
function simpletest_script_init(InputInterface $input, ClassLoader $autoloader): void {
  $host = 'localhost';
  $path = '';
  $port = '80';
  $php = "";

  // Determine location of php command automatically, unless a command line
  // argument is supplied.
  if ($php_env = getenv('_')) {
    // '_' is an environment variable set by the shell. It contains the command
    // that was executed.
    $php = $php_env;
  }

  if ($sudo = getenv('SUDO_COMMAND')) {
    // 'SUDO_COMMAND' is an environment variable set by the sudo program.
    // This will be set if the script is run directly by sudo or if the
    // script is run under a shell started by sudo.
    if (str_contains($sudo, basename(__FILE__))) {
      // This script may have been directly run by sudo. $php may have the
      // path to sudo from getenv('_') if run with the -E option.
      // Extract what may be the PHP interpreter.
      [$php] = explode(' ', $sudo, 2);
    }
  }

  if ($input->getOption('php')) {
    // Caller has specified path to php. Override auto-detection.
    $php = $input->getOption('php');
  }

  if ($php == "") {
    simpletest_script_print_error($input, 'Unable to automatically determine the path to the PHP interpreter. Supply the --php command line argument.');
    exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
  }
  else {
    $input->setOption('php', $php);
  }

  // Detect if we're in the top-level process using the private 'execute-test'
  // argument. Determine if being run on drupal.org's testing infrastructure
  // using the presence of 'drupalci' in the sqlite argument.
  // @todo https://www.drupal.org/project/drupalci_testbot/issues/2860941 Use
  //   better environment variable to detect DrupalCI.
  if (!$input->getOption('execute-test') && preg_match('/drupalci/', $input->getOption('sqlite') ?? '')) {
    // Update PHPUnit if needed and possible. There is a later check once the
    // autoloader is in place to ensure we're on the correct version. We need to
    // do this before the autoloader is in place to ensure that it is correct.
    $composer = ($composer = rtrim('\\' === DIRECTORY_SEPARATOR ? preg_replace('/[\r\n].*/', '', `where.exe composer.phar`) : `which composer.phar`))
      ? $php . ' ' . escapeshellarg($composer)
      : 'composer';
    passthru("$composer run-script drupal-phpunit-upgrade-check");
  }

  // Get URL from arguments.
  if ($input->getOption('url')) {
    $parsed_url = parse_url($input->getOption('url'));
    $host = $parsed_url['host'] . (isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '');
    $path = isset($parsed_url['path']) ? rtrim(rtrim($parsed_url['path']), '/') : '';
    $port = $parsed_url['port'] ?? $port;
    if ($path == '/') {
      $path = '';
    }
    // If the passed URL schema is 'https' then setup the $_SERVER variables
    // properly so that testing will run under HTTPS.
    if ($parsed_url['scheme'] == 'https') {
      $_SERVER['HTTPS'] = 'on';
    }
  }

  if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $base_url = 'https://';
  }
  else {
    $base_url = 'http://';
  }
  $base_url .= $host;
  if ($path !== '') {
    $base_url .= $path;
  }
  putenv('SIMPLETEST_BASE_URL=' . $base_url);
  $_SERVER['HTTP_HOST'] = $host;
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  $_SERVER['SERVER_ADDR'] = '127.0.0.1';
  $_SERVER['SERVER_PORT'] = $port;
  $_SERVER['SERVER_SOFTWARE'] = NULL;
  $_SERVER['SERVER_NAME'] = 'localhost';
  $_SERVER['REQUEST_URI'] = $path . '/';
  $_SERVER['REQUEST_METHOD'] = 'GET';
  $_SERVER['SCRIPT_NAME'] = $path . '/index.php';
  $_SERVER['SCRIPT_FILENAME'] = $path . '/index.php';
  $_SERVER['PHP_SELF'] = $path . '/index.php';
  $_SERVER['HTTP_USER_AGENT'] = 'Drupal command line';

  if ($input->getOption('concurrency') > 1) {
    $directory = FileSystem::getOsTemporaryDirectory();
    $test_symlink = @symlink(__FILE__, $directory . '/test_symlink');
    if (!$test_symlink) {
      throw new \RuntimeException('In order to use a concurrency higher than 1 the test system needs to be able to create symlinks in ' . $directory);
    }
    unlink($directory . '/test_symlink');
    putenv('RUN_TESTS_CONCURRENCY=' . $input->getOption('concurrency'));
  }

  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
    // Ensure that any and all environment variables are changed to https://.
    foreach ($_SERVER as $key => $value) {
      // Some values are NULL. Non-NULL values which are falsy will not contain
      // text to replace.
      if ($value) {
        $_SERVER[$key] = str_replace('http://', 'https://', $value);
      }
    }
  }

  chdir(realpath(__DIR__ . '/../..'));

  // Prepare the kernel.
  try {
    $request = Request::createFromGlobals();
    $kernel = TestRunnerKernel::createFromRequest($request, $autoloader);
    $kernel->boot();
    $kernel->preHandle($request);
  }
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }
}

/**
 * Sets up database connection info for running tests.
 *
 * If this script is executed from within a real Drupal installation, then this
 * function essentially performs nothing (unless the --sqlite or --dburl
 * parameters were passed).
 *
 * Otherwise, there are three database connections of concern:
 * - --sqlite: The test runner connection, providing access to database tables
 *   for recording test IDs and assertion results.
 * - --dburl: A database connection that is used as base connection info for all
 *   tests; i.e., every test will spawn from this connection. In case this
 *   connection uses e.g. SQLite, then all tests will run against SQLite. This
 *   is exposed as $databases['default']['default'] to Drupal.
 * - The actual database connection used within a test. This is the same as
 *   --dburl, but uses an additional database table prefix. This is
 *   $databases['default']['default'] within a test environment. The original
 *   connection is retained in
 *   $databases['simpletest_original_default']['default'] and restored after
 *   each test.
 *
 * @param bool $new
 *   Whether this process is a run-tests.sh master process. If TRUE, the SQLite
 *   database file specified by --sqlite (if any) is set up. Otherwise, database
 *   connections are prepared only.
 */
function simpletest_script_setup_database(InputInterface $input, $new = FALSE): void {
  // If there is an existing Drupal installation that contains a database
  // connection info in settings.php, then $databases['default']['default'] will
  // hold the default database connection already. This connection is assumed to
  // be valid, and this connection will be used in tests, so that they run
  // against e.g. MySQL instead of SQLite.
  // However, in case no Drupal installation exists, this default database
  // connection can be set and/or overridden with the --dburl parameter.
  if ($input->getOption('dburl')) {
    // Remove a possibly existing default connection (from settings.php).
    Database::removeConnection('default');
    try {
      $databases['default']['default'] = Database::convertDbUrlToConnectionInfo($input->getOption('dburl'), TRUE);
    }
    catch (\InvalidArgumentException $e) {
      simpletest_script_print_error($input, 'Invalid --dburl. Reason: ' . $e->getMessage());
      exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
    }
  }
  // Otherwise, use the default database connection from settings.php.
  else {
    $databases['default'] = Database::getConnectionInfo('default');
  }

  if (isset($databases['default']['default'])) {
    Database::addConnectionInfo('default', 'default', $databases['default']['default']);
  }
}

/**
 * Sets up the test runs results storage.
 */
function simpletest_script_setup_test_run_results_storage(InputInterface $input, $new = FALSE) {
  $databases['default'] = Database::getConnectionInfo('default');

  // If no --sqlite parameter has been passed, then the test runner database
  // connection is the default database connection.
  $sqlite = $input->getOption('sqlite');
  if (!$sqlite) {
    $sqlite = FALSE;
    $databases['test-runner']['default'] = $databases['default']['default'];
  }
  // Otherwise, set up a SQLite connection for the test runner.
  else {
    if (!str_starts_with($sqlite, '/')) {
      $sqlite = DRUPAL_ROOT . '/' . $sqlite;
    }
    $databases['test-runner']['default'] = [
      'driver' => 'sqlite',
      'database' => $sqlite,
      'prefix' => '',
    ];
    // Create the test runner SQLite database, unless it exists already.
    if ($new && !file_exists($sqlite)) {
      if (!is_dir(dirname($sqlite))) {
        mkdir(dirname($sqlite));
      }
      touch($sqlite);
    }
  }

  // Add the test runner database connection.
  Database::addConnectionInfo('test-runner', 'default', $databases['test-runner']['default']);

  // Create the test result schema.
  try {
    $test_run_results_storage = new SimpletestTestRunResultsStorage(Database::getConnection('default', 'test-runner'));
  }
  catch (\PDOException $e) {
    simpletest_script_print_error($input, $databases['test-runner']['default']['driver'] . ': ' . $e->getMessage());
    exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
  }
  if ($new && $sqlite) {
    try {
      $test_run_results_storage->buildTestingResultsEnvironment($input->getOption('keep-results-table'));
    }
    catch (Exception $e) {
      echo (string) $e;
      exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
    }
  }
  // Verify that the test result database schema exists by checking one table.
  try {
    if (!$test_run_results_storage->validateTestingResultsEnvironment()) {
      simpletest_script_print_error($input, 'Missing test result database schema. Use the --sqlite parameter.');
      exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
    }
  }
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }

  return $test_run_results_storage;
}

/**
 * Execute a batch of tests.
 */
function simpletest_script_execute_batch(InputInterface $input, TestRunResultsStorageInterface $test_run_results_storage, $test_classes, string $script_basename) {
  global $test_ids;

  $total_status = SIMPLETEST_SCRIPT_EXIT_SUCCESS;

  // Multi-process execution.
  $children = [];
  while (!empty($test_classes) || !empty($children)) {
    while (count($children) < $input->getOption('concurrency')) {
      if (empty($test_classes)) {
        break;
      }

      try {
        $test_run = TestRun::createNew($test_run_results_storage);
      }
      catch (Exception $e) {
        echo (string) $e;
        exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
      }
      $test_ids[] = $test_run->id();

      $test_class = array_shift($test_classes);
      // Fork a child process.
      $command = simpletest_script_command($input, $test_run, $test_class, $script_basename);
      try {
        $process = new Process($command);
        $process->start();
      }
      catch (\Exception $e) {
        echo get_class($e) . ": " . $e->getMessage() . "\n";
        echo "Unable to fork test process. Aborting.\n";
        exit(SIMPLETEST_SCRIPT_EXIT_SUCCESS);
      }

      // Register our new child.
      $children[] = [
        'process' => $process,
        'test_run' => $test_run,
        'class' => $test_class,
      ];
    }

    // Wait for children every 2ms.
    usleep(2000);

    // Check if some children finished.
    foreach ($children as $cid => $child) {
      if ($child['process']->isTerminated()) {
        // The child exited.
        echo $child['process']->getOutput();
        $errorOutput = $child['process']->getErrorOutput();
        if ($errorOutput) {
          echo 'ERROR: ' . $errorOutput;
        }
        if (in_array($child['process']->getExitCode(), [SIMPLETEST_SCRIPT_EXIT_FAILURE, SIMPLETEST_SCRIPT_EXIT_ERROR])) {
          $total_status = max($child['process']->getExitCode(), $total_status);
        }
        elseif ($child['process']->getExitCode()) {
          $message = 'FATAL ' . $child['class'] . ': test runner returned an unexpected error code (' . $child['process']->getExitCode() . ').';
          echo $message . "\n";
          $total_status = max(SIMPLETEST_SCRIPT_EXIT_EXCEPTION, $total_status);
          if ($input->getOption('die-on-fail')) {
            $test_db = new TestDatabase($child['test_run']->getDatabasePrefix());
            $test_directory = $test_db->getTestSitePath();
            echo 'Test database and files kept and test exited immediately on fail so should be reproducible if you change settings.php to use the database prefix ' . $child['test_run']->getDatabasePrefix() . ' and config directories in ' . $test_directory . "\n";
            $input->setOption('keep-results', TRUE);
            // Exit repeat loop immediately.
            $input->setOption('repeat', -1);
          }
        }

        // Remove this child.
        unset($children[$cid]);
      }
    }
  }
  return $total_status;
}

/**
 * Run a PHPUnit-based test.
 */
function simpletest_script_run_phpunit(InputInterface $input, TestRun $test_run, $class) {
  $runner = PhpUnitTestRunner::create(\Drupal::getContainer());
  $start = microtime(TRUE);
  $results = $runner->execute($test_run, $class, $status, $input->getOption('color'));
  $time = microtime(TRUE) - $start;

  $runner->processPhpUnitResults($test_run, $results);

  $summaries = $runner->summarizeResults($results);
  foreach ($summaries as $class => $summary) {
    simpletest_script_reporter_display_summary($input, $class, $summary, $time);
  }
  return $status;
}

/**
 * Run a single test, bootstrapping Drupal if needed.
 */
function simpletest_script_run_one_test(InputInterface $input, TestRun $test_run, $test_class): void {
  try {
    if ($input->getOption('suppress-deprecations')) {
      putenv('SYMFONY_DEPRECATIONS_HELPER=disabled');
    }
    $status = simpletest_script_run_phpunit($input, $test_run, $test_class);
    exit($status);
  }
  // DrupalTestCase::run() catches exceptions already, so this is only reached
  // when an exception is thrown in the wrapping test runner environment.
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }
}

/**
 * Return a command used to run a test in a separate process.
 *
 * @param string $test_class
 *   The name of the test class to run.
 *
 * @return list<string>
 *   The list of command-line elements.
 */
function simpletest_script_command(InputInterface $input, TestRun $test_run, string $test_class, string $script_basename): array {
  $command = [];
  $command[] = $input->getOption('php');
  $command[] = './core/scripts/' . $script_basename;
  $command[] = '--url';
  $command[] = $input->getOption('url');
  if ($input->getOption('sqlite')) {
    $command[] = '--sqlite';
    $command[] = $input->getOption('sqlite');
  }
  if ($input->getOption('dburl')) {
    $command[] = '--dburl';
    $command[] = $input->getOption('dburl');
  }
  $command[] = '--php';
  $command[] = $input->getOption('php');
  $command[] = '--test-id';
  $command[] = $test_run->id();
  foreach (['verbose', 'keep-results', 'color', 'die-on-fail', 'suppress-deprecations'] as $arg) {
    if ($input->getOption($arg)) {
      $command[] = '--' . $arg;
    }
  }
  // --execute-test and class name needs to come last.
  $command[] = '--execute-test';
  $command[] = $test_class;

  return $command;
}

/**
 * Get list of tests based on arguments.
 *
 * If --all specified then return all available tests, otherwise reads list of
 * tests.
 *
 * @return array
 *   List of tests.
 */
function simpletest_script_get_test_list(InputInterface $input) {
  $test_discovery = PhpUnitTestDiscovery::instance()->setConfigurationFilePath(\Drupal::root() . \DIRECTORY_SEPARATOR . 'core');
  $test_list = [];
  $slow_tests = [];
  if ($input->getOption('all') || $input->getOption('module') || $input->getOption('directory')) {
    try {
      $groups = $test_discovery->getTestClasses($input->getOption('module'), $input->getOption('types'), $input->getOption('directory'));
      $warnings = $test_discovery->getWarnings();
      if (!empty($warnings)) {
        simpletest_script_print($input, "Test discovery warnings\n", SIMPLETEST_SCRIPT_COLOR_BRIGHT_WHITE);
        simpletest_script_print($input, "-----------------------\n", SIMPLETEST_SCRIPT_COLOR_BRIGHT_WHITE);
        foreach ($warnings as $warning) {
          simpletest_script_print($input, '* ' . $warning . "\n\n", SIMPLETEST_SCRIPT_COLOR_EXCEPTION);
        }
        echo "\n";
      }
    }
    catch (Exception $e) {
      echo (string) $e;
      exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
    }
    // Ensure that tests marked explicitly as @group #slow are run at the
    // beginning of each job.
    if (key($groups) === '#slow') {
      $slow_tests = array_shift($groups);
    }
    $not_slow_tests = [];
    foreach ($groups as $group => $tests) {
      $not_slow_tests = array_merge($not_slow_tests, $tests);
    }
    // Filter slow tests out of the not slow tests and ensure a unique list
    // since tests may appear in more than one group.
    $not_slow_tests = array_diff_key($not_slow_tests, $slow_tests);

    // If the tests are not being run in parallel, then ensure slow tests run
    // all together first.
    if ((int) $input->getOption('ci-parallel-node-total') <= 1 ) {
      sort_tests_by_type_and_methods($slow_tests);
      sort_tests_by_type_and_methods($not_slow_tests);
      $all_tests_list = array_merge($slow_tests, $not_slow_tests);
      assign_tests_sequence($all_tests_list);
      dump_tests_sequence($all_tests_list, $input);
      $test_list = array_keys($all_tests_list);
    }
    else {
      // Sort all tests by the number of test cases on the test class.
      // This is used in combination with @group #slow to start the slowest
      // tests first and distribute tests between test runners.
      sort_tests_by_public_method_count($slow_tests);
      sort_tests_by_public_method_count($not_slow_tests);
      $all_tests_list = array_merge($slow_tests, $not_slow_tests);
      assign_tests_sequence($all_tests_list);

      // Now set up a bin per test runner.
      $bin_count = (int) $input->getOption('ci-parallel-node-total');

      // Now loop over the slow tests and add them to a bin one by one, this
      // distributes the tests evenly across the bins.
      $binned_slow_tests = place_tests_into_bins($slow_tests, $bin_count);
      $slow_tests_for_job = $binned_slow_tests[$input->getOption('ci-parallel-node-index') - 1];

      // And the same for the rest of the tests.
      $binned_other_tests = place_tests_into_bins($not_slow_tests, $bin_count);
      $other_tests_for_job = $binned_other_tests[$input->getOption('ci-parallel-node-index') - 1];
      $test_list = array_merge($slow_tests_for_job, $other_tests_for_job);
      dump_bin_tests_sequence($input->getOption('ci-parallel-node-index'), $all_tests_list, $test_list, $input);
      $test_list = array_keys($test_list);
    }
  }
  else {
    if ($input->getOption('class')) {
      $test_list = [];
      foreach ($input->getArgument('tests') as $test_class) {
        [$class_name] = explode('::', $test_class, 2);
        if (class_exists($class_name)) {
          $test_list[] = $test_class;
        }
        else {
          try {
            $groups = $test_discovery->getTestClasses(NULL, $input->getOption('types'));
            foreach ($test_discovery->getWarnings() as $warning) {
              simpletest_script_print($input, $warning . "\n", SIMPLETEST_SCRIPT_COLOR_EXCEPTION);
            }
          }
          catch (Exception $e) {
            echo (string) $e;
            exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
          }
          $all_classes = [];
          foreach ($groups as $group) {
            $all_classes = array_merge($all_classes, array_keys($group));
          }
          simpletest_script_print_error($input, 'Test class not found: ' . $class_name);
          simpletest_script_print_alternatives($input, $class_name, $all_classes, 6);
          exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
        }
      }
    }
    elseif ($input->getOption('file')) {
      // Extract test case class names from specified files.
      foreach ($input->getArgument('tests') as $file) {
        if (!file_exists($file) || is_dir($file)) {
          simpletest_script_print_error($input, 'File not found: ' . $file);
          exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
        }
        $fileTests = current($test_discovery->getTestClasses(NULL, [], $file));
        $test_list = array_merge($test_list, $fileTests);
      }
      assign_tests_sequence($test_list);
      dump_tests_sequence($test_list, $input);
      $test_list = array_keys($test_list);
    }
    else {
      try {
        $groups = $test_discovery->getTestClasses(NULL, $input->getOption('types'));
        foreach ($test_discovery->getWarnings() as $warning) {
          simpletest_script_print($input, $warning . "\n", SIMPLETEST_SCRIPT_COLOR_EXCEPTION);
        }
      }
      catch (Exception $e) {
        echo (string) $e;
        exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
      }
      // Store all the groups so we can suggest alternatives if we need to.
      $all_groups = array_keys($groups);
      // Verify that the groups exist.
      if (!empty($unknown_groups = array_diff($input->getArgument('tests'), $all_groups))) {
        $first_group = reset($unknown_groups);
        simpletest_script_print_error($input, 'Test group not found: ' . $first_group);
        simpletest_script_print_alternatives($input, $first_group, $all_groups);
        exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
      }
      // Merge the tests from the groups together.
      foreach ($input->getArgument('tests') as $group_name) {
        $test_list = array_merge($test_list, $groups[$group_name]);
      }
      assign_tests_sequence($test_list);
      dump_tests_sequence($test_list, $input);
      // Ensure our list of tests contains only one entry for each test.
      $test_list = array_keys($test_list);
    }
  }

  if (empty($test_list)) {
    simpletest_script_print_error($input, 'No valid tests were specified.');
    exit(SIMPLETEST_SCRIPT_EXIT_FAILURE);
  }

  return $test_list;
}

/**
 * Sort tests by test type and number of public methods.
 */
function sort_tests_by_type_and_methods(array &$tests): void {
  uasort($tests, function ($a, $b) {
    if (get_test_type_weight($a['name']) === get_test_type_weight($b['name'])) {
      return $b['tests_count'] <=> $a['tests_count'];
    }
    return get_test_type_weight($b['name']) <=> get_test_type_weight($a['name']);
  });
}

/**
 * Sort tests by the number of public methods in the test class.
 *
 * Tests with several methods take longer to run than tests with a single
 * method all else being equal, so this allows tests runs to be sorted by
 * approximately the slowest to fastest tests. Tests that are exceptionally
 * slow can be added to the '#slow' group so they are placed first in each
 * test run regardless of the number of methods.
 *
 * @param string[] $tests
 *   An array of test class names.
 */
function sort_tests_by_public_method_count(array &$tests): void {
  uasort($tests, function (array $a, array $b) {
    return $b['tests_count'] <=> $a['tests_count'];
  });
}

/**
 * Weights a test class based on which test base class it extends.
 *
 * @param string $class
 *   The test class name.
 */
function get_test_type_weight(string $class): int {
  return match(TRUE) {
    is_subclass_of($class, WebDriverTestBase::class) => 3,
    is_subclass_of($class, BrowserTestBase::class) => 2,
    is_subclass_of($class, BuildTestBase::class) => 2,
    is_subclass_of($class, KernelTestBase::class) => 1,
    default => 0,
  };
}

/**
 * Assigns the test sequence.
 *
 * @param array $tests
 *   The array of test class info.
 */
function assign_tests_sequence(array &$tests): void {
  $i = 0;
  foreach ($tests as &$testInfo) {
    $testInfo['sequence'] = ++$i;
  }
}

/**
 * Dumps the list of tests in order of execution after sorting.
 *
 * @param array $tests
 *   The array of test class info.
 * @param \Symfony\Component\Console\Input\InputInterface $input
 *   The command line arguments.
 */
function dump_tests_sequence(array $tests, InputInterface $input): void {
  if (!$input->getOption('debug-discovery')) {
    return;
  }
  echo "Test execution sequence\n";
  echo "-----------------------\n\n";
  echo " Seq Slow? Group            Cnt Class\n";
  echo "-----------------------------------------\n";
  $i = 0;
  foreach ($tests as $testInfo) {
    echo sprintf(
      "%4d %5s %15s %4d %s\n",
      $testInfo['sequence'],
      in_array('#slow', $testInfo['groups']) ? '#slow' : '',
      trim_with_ellipsis($testInfo['group'], 15, \STR_PAD_RIGHT),
      $testInfo['tests_count'],
      trim_with_ellipsis($testInfo['name'], 60, \STR_PAD_LEFT),
    );
  }
  echo "-----------------------------------------\n\n";
}

/**
 * Distribute tests into bins.
 *
 * The given array of tests is split into the available bins. The distribution
 * starts with the first test, placing the first test in the first bin, the
 * second test in the second bin and so on. This results each bin having a
 * similar number of test methods to run in total.
 *
 * @param string[] $tests
 *   An array of test class names.
 * @param int $bin_count
 *   The number of bins available.
 *
 * @return array
 *   An associative array of bins and the test class names in each bin.
 */
function place_tests_into_bins(array $tests, int $bin_count) {
  // Create a bin corresponding to each parallel test job.
  $bins = array_fill(0, $bin_count, []);
  // Go through each test and add them to one bin at a time.
  $i = 0;
  foreach ($tests as $key => $test) {
    $bins[($i++ % $bin_count)][$key] = $test;
  }
  return $bins;
}

/**
 * Dumps the list of tests in order of execution for a bin.
 *
 * @param int $bin
 *   The bin.
 * @param array $allTests
 *   The list of all test classes discovered.
 * @param array $tests
 *   The list of test class to run for this bin.
 * @param \Symfony\Component\Console\Input\InputInterface $input
 *   The command line arguments.
 */
function dump_bin_tests_sequence(int $bin, array $allTests, array $tests, InputInterface $input): void {
  if (!$input->getOption('debug-discovery')) {
    return;
  }
  echo "Test execution sequence. ";
  echo "Tests marked *** will be executed in this PARALLEL BIN #{$bin}.\n";
  echo "-------------------------------------------------------------------------------------\n\n";
  echo "Bin  Seq Slow? Group            Cnt Class\n";
  echo "--------------------------------------------\n";
  foreach ($allTests as $testInfo) {
    $inBin = isset($tests[$testInfo['name']]);
    $message = sprintf(
      "%s %4d %5s %15s %4d %s\n",
      $inBin ? "***" : "   ",
      $testInfo['sequence'],
      in_array('#slow', $testInfo['groups']) ? '#slow' : '',
      trim_with_ellipsis($testInfo['group'], 15, \STR_PAD_RIGHT),
      $testInfo['tests_count'],
      trim_with_ellipsis($testInfo['name'], 60, \STR_PAD_LEFT),
    );
    simpletest_script_print($input, $message, $inBin ? SIMPLETEST_SCRIPT_COLOR_BRIGHT_WHITE : SIMPLETEST_SCRIPT_COLOR_GRAY);
  }
  echo "-------------------------------------------------\n\n";
}

/**
 * Initialize the reporter.
 */
function simpletest_script_reporter_init(InputInterface $input): void {
  global $test_list, $results_map;

  $results_map = [
    'pass' => 'Pass',
    'fail' => 'Fail',
    'error' => 'Error',
    'skipped' => 'Skipped',
    'exception' => 'Exception',
    'debug' => 'Log',
  ];

  // Tell the user about what tests are to be run.
  if ($input->getOption('all')) {
    echo "All tests will run.\n\n";
  }
  else {
    echo "Tests to be run:\n";
    foreach ($test_list as $class_name) {
      echo "  - $class_name\n";
    }
    echo "\n";
  }

  echo "Test run started:\n";
  echo "  " . date('l, F j, Y - H:i', $_SERVER['REQUEST_TIME']) . "\n";
  Timer::start('run-tests');
  echo "\n";

  echo "Test summary\n";
  echo "------------\n";
  echo "\n";
}

/**
 * Displays the assertion result summary for a single test class.
 *
 * @param string $class
 *   The test class name that was run.
 * @param array $results
 *   The assertion results using #pass, #fail, #exception, #debug array keys.
 * @param float|null $duration
 *   The time taken for the test to complete.
 */
function simpletest_script_reporter_display_summary(InputInterface $input, $class, $results, $duration = NULL): void {
  // Output all test results vertically aligned.
  $summary = [str_pad($results['#pass'], 4, " ", STR_PAD_LEFT) . ' passed'];
  if ($results['#fail']) {
    $summary[] = $results['#fail'] . ' failed';
  }
  if ($results['#error']) {
    $summary[] = $results['#error'] . ' errored';
  }
  if ($results['#skipped']) {
    $summary[] = $results['#skipped'] . ' skipped';
  }
  if ($results['#exception']) {
    $summary[] = $results['#exception'] . ' exception(s)';
  }
  if ($results['#debug']) {
    $summary[] = $results['#debug'] . ' log(s)';
  }

  if ($results['#time']) {
    $time = sprintf('%8.3fs', $results['#time']);
  }
  else {
    $time = sprintf('%8.3fs', $duration);
  }

  $output = vsprintf('%s %s %s', [$time, trim_with_ellipsis($class, 70, STR_PAD_LEFT), implode(', ', $summary)]);
  $status = ($results['#fail'] || $results['#exception'] || $results['#error'] ? 'fail' : 'pass');
  simpletest_script_print($input, $output . "\n", simpletest_script_color_code($status));
}

/**
 * Display jUnit XML test results.
 */
function simpletest_script_reporter_write_xml_results(InputInterface $input, TestRunResultsStorageInterface $test_run_results_storage): void {
  global $test_ids, $results_map;

  try {
    $results = simpletest_script_load_messages_by_test_id($input, $test_run_results_storage, $test_ids);
  }
  catch (Exception $e) {
    echo (string) $e;
    exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
  }

  $test_class = '';
  $xml_files = [];

  foreach ($results as $result) {
    if (isset($results_map[$result->status])) {
      if ($result->test_class != $test_class) {
        // We've moved onto a new class, so write the last classes results to a
        // file:
        if (isset($xml_files[$test_class])) {
          file_put_contents($input->getOption('xml') . '/' . str_replace('\\', '_', $test_class) . '.xml', $xml_files[$test_class]['doc']->saveXML());
          unset($xml_files[$test_class]);
        }
        $test_class = $result->test_class;
        if (!isset($xml_files[$test_class])) {
          $doc = new DOMDocument('1.0', 'utf-8');
          $root = $doc->createElement('testsuite');
          $root = $doc->appendChild($root);
          $xml_files[$test_class] = ['doc' => $doc, 'suite' => $root];
        }
      }

      // For convenience:
      $dom_document = &$xml_files[$test_class]['doc'];

      // Create the XML element for this test case:
      $case = $dom_document->createElement('testcase');
      $case->setAttribute('classname', $test_class);
      if (str_contains($result->function, '->')) {
        [$class, $name] = explode('->', $result->function, 2);
      }
      else {
        $name = $result->function;
      }
      $case->setAttribute('name', $name);

      // Passes get no further attention, but failures and exceptions get to add
      // more detail:
      if ($result->status == 'fail') {
        $fail = $dom_document->createElement('failure');
        $fail->setAttribute('type', 'failure');
        $fail->setAttribute('message', $result->message_group);
        $text = $dom_document->createTextNode($result->message);
        $fail->appendChild($text);
        $case->appendChild($fail);
      }
      elseif ($result->status == 'exception') {
        // In the case of an exception the $result->function may not be a class
        // method so we record the full function name:
        $case->setAttribute('name', $result->function);

        $fail = $dom_document->createElement('error');
        $fail->setAttribute('type', 'exception');
        $fail->setAttribute('message', $result->message_group);
        $full_message = $result->message . "\n\nline: " . $result->line . "\nfile: " . $result->file;
        $text = $dom_document->createTextNode($full_message);
        $fail->appendChild($text);
        $case->appendChild($fail);
      }
      // Append the test case XML to the test suite:
      $xml_files[$test_class]['suite']->appendChild($case);
    }
  }
  // The last test case hasn't been saved to a file yet, so do that now:
  if (isset($xml_files[$test_class])) {
    file_put_contents($input->getOption('xml') . '/' . str_replace('\\', '_', $test_class) . '.xml', $xml_files[$test_class]['doc']->saveXML());
    unset($xml_files[$test_class]);
  }
}

/**
 * Stop the test timer.
 */
function simpletest_script_reporter_timer_stop(): void {
  echo "\n";
  $end = Timer::stop('run-tests');
  echo "Test run duration: " . \Drupal::service('date.formatter')->formatInterval((int) ($end['time'] / 1000));
  echo "\n\n";
}

/**
 * Display test results.
 */
function simpletest_script_reporter_display_results(InputInterface $input, TestRunResultsStorageInterface $test_run_results_storage): void {
  global $test_ids, $results_map;

  if ($input->getOption('verbose')) {
    // Report results.
    echo "Detailed test results\n";
    echo "---------------------\n";

    try {
      $results = simpletest_script_load_messages_by_test_id($input, $test_run_results_storage, $test_ids);
    }
    catch (Exception $e) {
      echo (string) $e;
      exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
    }
    $test_class = '';
    foreach ($results as $result) {
      if (isset($results_map[$result->status])) {
        if ($result->test_class != $test_class) {
          // Display test class every time results are for new test class.
          echo "\n\n---- $result->test_class ----\n\n\n";
          $test_class = $result->test_class;

          // Print table header.
          echo "Status      Duration Info                                                                               \n";
          echo "--------------------------------------------------------------------------------------------------------\n";
        }

        simpletest_script_format_result($input, $result);
      }
    }
  }
}

/**
 * Format the result so that it fits within 80 characters.
 *
 * @param object $result
 *   The result object to format.
 */
function simpletest_script_format_result(InputInterface $input, $result): void {
  global $results_map, $color;

  $summary = sprintf("%-9.9s %9.3fs %s\n", $results_map[$result->status], $result->time, trim_with_ellipsis($result->function, 80, STR_PAD_LEFT));

  simpletest_script_print($input, $summary, simpletest_script_color_code($result->status));

  if ($result->message === '' || in_array($result->status, ['pass', 'fail', 'error'])) {
    return;
  }

  $message = trim(strip_tags($result->message));
  if ($input->getOption('non-html')) {
    $message = Html::decodeEntities($message);
  }
  $lines = explode("\n", $message);
  foreach ($lines as $line) {
    echo "    $line\n";
  }
}

/**
 * Print error messages so the user will notice them.
 *
 * Print error message prefixed with "  ERROR: " and displayed in fail color if
 * color output is enabled.
 *
 * @param string $message
 *   The message to print.
 */
function simpletest_script_print_error(?InputInterface $input, $message): void {
  simpletest_script_print($input, "  ERROR: $message\n", SIMPLETEST_SCRIPT_COLOR_FAIL);
}

/**
 * Print a message to the console, using a color.
 *
 * @param string $message
 *   The message to print.
 * @param string|int $color_code
 *   The color code to use for coloring.
 */
function simpletest_script_print(?InputInterface $input, $message, $color_code): void {
  if ($input && $input->getOption('color')) {
    echo "\033[" . $color_code . "m" . $message . "\033[0m";
  }
  else {
    echo $message;
  }
}

/**
 * Get the color code associated with the specified status.
 *
 * @param string $status
 *   The status string to get code for. Special cases are: 'pass', 'fail', or
 *   'exception'.
 *
 * @return int
 *   Color code. Returns 0 for default case.
 */
function simpletest_script_color_code($status) {
  return match ($status) {
    'pass' => SIMPLETEST_SCRIPT_COLOR_PASS,
    'fail', 'error', 'exception' => SIMPLETEST_SCRIPT_COLOR_FAIL,
    'skipped' => SIMPLETEST_SCRIPT_COLOR_YELLOW,
    'debug' => SIMPLETEST_SCRIPT_COLOR_CYAN,
    default => 0,
  };
}

/**
 * Prints alternative test names.
 *
 * Searches the provided array of string values for close matches based on the
 * Levenshtein algorithm.
 *
 * @param string $string
 *   A string to test.
 * @param array $array
 *   A list of strings to search.
 * @param int $degree
 *   The matching strictness. Higher values return fewer matches. A value of
 *   4 means that the function will return strings from $array if the candidate
 *   string in $array would be identical to $string by changing 1/4 or fewer of
 *   its characters.
 *
 * @see http://php.net/manual/function.levenshtein.php
 */
function simpletest_script_print_alternatives(InputInterface $input, $string, $array, $degree = 4): void {
  $alternatives = [];
  foreach ($array as $item) {
    $lev = levenshtein($string, $item);
    if ($lev <= strlen($item) / $degree || str_contains($string, $item)) {
      $alternatives[] = $item;
    }
  }
  if (!empty($alternatives)) {
    simpletest_script_print($input, "  Did you mean?\n", SIMPLETEST_SCRIPT_COLOR_FAIL);
    foreach ($alternatives as $alternative) {
      simpletest_script_print($input, "  - $alternative\n", SIMPLETEST_SCRIPT_COLOR_FAIL);
    }
  }
}

/**
 * Loads test result messages from the database.
 *
 * Messages are ordered by test class and message id.
 *
 * @param array $test_ids
 *   Array of test IDs of the messages to be loaded.
 *
 * @return array
 *   Array of test result messages from the database.
 */
function simpletest_script_load_messages_by_test_id(InputInterface $input, TestRunResultsStorageInterface $test_run_results_storage, $test_ids) {
  $results = [];

  // Sqlite has a maximum number of variables per query. If required, the
  // database query is split into chunks.
  if (count($test_ids) > SIMPLETEST_SCRIPT_SQLITE_VARIABLE_LIMIT && $input->getOption('sqlite')) {
    $test_id_chunks = array_chunk($test_ids, SIMPLETEST_SCRIPT_SQLITE_VARIABLE_LIMIT);
  }
  else {
    $test_id_chunks = [$test_ids];
  }

  foreach ($test_id_chunks as $test_id_chunk) {
    try {
      $result_chunk = [];
      foreach ($test_id_chunk as $test_id) {
        $test_run = TestRun::get($test_run_results_storage, $test_id);
        $result_chunk = array_merge($result_chunk, $test_run->getLogEntriesByTestClass());
      }
    }
    catch (Exception $e) {
      echo (string) $e;
      exit(SIMPLETEST_SCRIPT_EXIT_EXCEPTION);
    }
    if ($result_chunk) {
      $results = array_merge($results, $result_chunk);
    }
  }

  return $results;
}

/**
 * Trims a string adding a leading or trailing ellipsis.
 *
 * @param string $input
 *   The input string.
 * @param int $length
 *   The exact trimmed string length.
 * @param int $side
 *   Leading or trailing ellipsis.
 *
 * @return string
 *   The trimmed string.
 */
function trim_with_ellipsis(string $input, int $length, int $side): string {
  if (strlen($input) < $length) {
      return str_pad($input, $length, ' ', \STR_PAD_RIGHT);
  }
  elseif (strlen($input) > $length) {
      return match($side) {
        \STR_PAD_RIGHT => substr($input, 0, $length - 1) . '…',
        default => '…' . substr($input, -$length + 1),
      };
  }
  return $input;
}
