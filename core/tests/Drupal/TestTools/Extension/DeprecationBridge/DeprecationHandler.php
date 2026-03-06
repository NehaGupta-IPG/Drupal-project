<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\Core\Database\Database;
use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber\Deprecation;
use Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber\PhpDeprecation;
use Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber\TestFinished;
use Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber\TestPreparationStarted;
use Drupal\TestTools\Extension\DeprecationBridge\EventSubscriber\TestRunnerFinished;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Runner\ErrorHandler as PhpUnitErrorHandler;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration as PhpUnitConfiguration;
use PHPUnit\Util\Filesystem;
use Symfony\Component\ErrorHandler\DebugClassLoader;

// cspell:ignore depsta

/**
 * Drupal's PHPUnit extension to manage code deprecation.
 *
 * In the future this extension might be dropped if PHPUnit adds support for
 * ignoring a specified list of deprecations.
 *
 * @internal
 */
final class DeprecationHandler implements Extension {

  /**
   * The path to the statistics staging file.
   */
  private string $statisticsStagingFilePath;

  /**
   * The path to the artifacts base directory.
   *
   * @todo remove.
   */
  private string $directory;

  /**
   * The PHP major/minor version in use.
   *
   * @todo remove.
   */
  private string $phpVersion;

  /**
   * The database driver used by the SUT.
   *
   * @todo remove.
   */
  private ?string $databaseDriver = NULL;

  /**
   * The database version used by the SUT.
   *
   * @todo remove.
   */
  private ?string $databaseVersion = NULL;

  /**
   * Statistics for the currently executing test case.
   *
   * @see \Drupal\TestTools\Extension\DeprecationBridge\TestPreparationStartedSubscriber
   * @see \Drupal\TestTools\Extension\DeprecationBridge\TestFinishedSubscriber
   */
  private static ?array $currentTest = NULL;

  /**
   * Remove.
   *
   * @todo remove.
   */
  public static function preBootstrap(PhpUnitConfiguration $configuration): void {
    $config = Configuration::instance();

    // Find the extension parameters from current PHPUnit configuration.
    $extensionBootstrappers = $configuration->extensionBootstrappers();
    $parameters = [];
    foreach ($extensionBootstrappers as $bootstrapper) {
      if ($bootstrapper['className'] === self::class) {
        $parameters = $bootstrapper['parameters'];
        break;
      }
    }
    $config->load($parameters);

    // Get the legacy configuration provided via Symfony env variable.
    $config->load(self::legacySymfonyConfiguration());

    // Determine if project ignores are enabled. If so, loads the deprecation
    // patterns to be ignored from the ignore file and sets the error handler
    // to Drupal\TestTools\ErrorHandler\BootstrapErrorHandler. This allows to
    // capture deprecations triggered by PHP or by the DebugClassLoader, that
    // can occur before tests' ::setUp() methods are called.
    if ($config->projectIgnoresEnabled()) {
      $config->loadDeprecationIgnorePatterns();
      // We pass an instance of the PHPUnit error handler to redirect any error
      // not managed by our layer back to PHPUnit.
      set_error_handler(new BootstrapErrorHandler(PhpUnitErrorHandler::instance()));
    }

    // Enable the DebugClassLoader to get deprecations for methods' signature
    // changes.
    if ($config->debugClassLoaderEnabled()) {
      DebugClassLoader::enable();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function bootstrap(
    PhpUnitConfiguration $configuration,
    Facade $facade,
    ParameterCollection $parameters,
  ): void {
    $config = Configuration::instance();

    // Bail out if the project ignores were disabled in ::preBootstrap().
    if (!$config->projectIgnoresEnabled()) {
      return;
    }

    if (!$config->collectStatsEnabled()) {
      return;
    }
    // Determine staging file path.
    $this->statisticsStagingFilePath = tempnam(sys_get_temp_dir(), 'depsta');

    // Set an environment variable with the configuration, so that isolated
    // tests can access it.
    $config = json_encode([
      'stagingFilePath' => $this->statisticsStagingFilePath,
    ]);
    putenv('_DRUPAL_DEPRECATION_HANDLER_CONFIG=' . $config);

    $facade->registerSubscriber(new TestPreparationStarted($this));
    $facade->registerSubscriber(new Deprecation($this));
    $facade->registerSubscriber(new PhpDeprecation($this));
    $facade->registerSubscriber(new TestFinished($this));
    $facade->registerSubscriber(new TestRunnerFinished($this));

    // Artifacts directory.
    // $directory = $parameters->get('directory');
    $directory = 'test-artifacts/jobs/' . getenv('CI_JOB_ID');
    Filesystem::createDirectory($directory);
    $realDirectory = realpath($directory);
    if ($realDirectory === FALSE || !is_dir($realDirectory) || !is_writable($realDirectory)) {
      throw new \RuntimeException("Test artifacts directory {$directory} is not a writable directory.");
    }
    $this->directory = $realDirectory;

    // PHP major/minor version.
    preg_match('/^\d+\.\d+/', \PHP_VERSION, $matches);
    $this->phpVersion = $matches[0];

    // Database driver.
    if (!empty(getenv('SIMPLETEST_DB'))) {
      $currDir = getcwd();
      chdir(dirname(__DIR__, 6));
      $dbInfo = Database::convertDbUrlToConnectionInfo(getenv('SIMPLETEST_DB'), TRUE);
      chdir($currDir);
      $this->databaseDriver = $dbInfo['driver'];
    }

    // Database version.
    if (!empty(getenv('_TARGET_DB'))) {
      $this->databaseVersion = getenv('_TARGET_DB');
    }

    // Set an environment variable with the configuration, so that isolated
    // tests can access it.
    $config = json_encode([
      'directory' => $this->directory,
      'php_version' => $this->phpVersion,
      'database_driver' => $this->databaseDriver,
      'database_version' => $this->databaseVersion,
    ]);
    putenv('_DRUPAL_TEST_ARTIFACT_CONFIG=' . $config);
  }

  /**
   * Returns the legacy configuration provided via Symfony env variable.
   *
   * For historical reasons, the configuration is stored in the
   * SYMFONY_DEPRECATIONS_HELPER environment variable.
   *
   * @return array|false
   *   An array of configuration variables.
   */
  private static function legacySymfonyConfiguration(): array {
    $environmentVariable = getenv('SYMFONY_DEPRECATIONS_HELPER');
    if ($environmentVariable === FALSE) {
      return [];
    }
    if ($environmentVariable === 'disabled') {
      return [
        'enableProjectIgnores' => FALSE,
        'enableDebugClassLoader' => FALSE,
      ];
    }
    parse_str($environmentVariable, $configuration);
    $ret = [
      'enableProjectIgnores' => TRUE,
      'enableDebugClassLoader' => TRUE,
    ];
    if (isset($configuration['ignoreFile'])) {
      $ret['projectIgnoreFile'] = $configuration['ignoreFile'];
    }
    return $ret;
  }

  /**
   * Dump statistics after test runner end.
   */
  public function processStatisticsArtifact(): void {
    assert(Configuration::collectStatsEnabled(), __METHOD__ . '() must not be called if deprecation statistics collection is not enabled.');

    $content = @file_get_contents($this->statisticsStagingFilePath);
    $statisticsArtifactDirectory = $this->directory . \DIRECTORY_SEPARATOR . 'deprecation-handler';
    Filesystem::createDirectory($statisticsArtifactDirectory);

    $test_run_id = getenv('DRUPAL_TEST_RUN_ID');
    if (!empty($test_run_id)) {
      $dumpFileName = "deprecation-ignore-stats-{$test_run_id}.json";
    }
    else {
      $dumpFileName = "deprecation-ignore-stats.json";
    }

    $encodedStats = explode("\n", $content);
    $stats = [
      'metadata' => $this->metadata(),
      'skips' => [],
      'tests' => [],
    ];
    foreach ($encodedStats as $encodedStat) {
      $stat = (array) json_decode(base64_decode($encodedStat));
      // @todo echo "\n********\n";.
      // @todo var_export($stat);.
      if (empty($stat)) {
        continue;
      }
      if ($stat['status'] === 'ignored_by_project') {
        $stats['skips'][$stat['ignore-pattern']] = isset($stats['skips'][$stat['ignore-pattern']]) ? $stats['skips'][$stat['ignore-pattern']] + 1 : 1;
      }
      if ($stat['status'] === 'test_stats') {
        $stats['tests'][$stat['test']] = $stat['ignoredByPhpunit'];
      }
    }
    // @todo echo "\n********\n";.

    file_put_contents(
      $statisticsArtifactDirectory . \DIRECTORY_SEPARATOR . $dumpFileName,
      json_encode($stats),
    );
  }

  /**
   * Write a statistic entry.
   */
  private static function writeStatisticEntry(string $status, array $stat): void {
    assert(Configuration::collectStatsEnabled(), __METHOD__ . '() must not be called if deprecation statistics collection is not enabled.');

    $stat['status'] = $status;

    $configEnv = getenv('_DRUPAL_DEPRECATION_HANDLER_CONFIG');

    if (empty($configEnv)) {
      // If no config available, we cannot record the stats.
      return;
    }

    $config = (array) json_decode($configEnv);

    file_put_contents(
      $config['stagingFilePath'],
      base64_encode(json_encode($stat)) . "\n",
      \FILE_APPEND,
    );
  }

  /**
   * Determines if an actual deprecation is ignored by the project.
   *
   * Deprecations that match the patterns included in the ignore file should
   * be ignored.
   *
   * @param \PHPUnit\Event\Code\TestMethod|null $testMethod
   *   The test case being executed.
   * @param string $deprecationMessage
   *   The actual deprecation message triggered via trigger_error().
   */
  public static function isIgnoredByProject(?string $testMethod, string $deprecationMessage): bool {
    if (!Configuration::projectIgnoresEnabled()) {
      return FALSE;
    }
    foreach (Configuration::deprecationIgnorePatterns() as $pattern) {
      $result = @preg_filter($pattern, '$0', $deprecationMessage);
      if (preg_last_error() !== \PREG_NO_ERROR) {
        throw new \RuntimeException(preg_last_error_msg());
      }
      if ((bool) $result) {
        if (Configuration::collectStatsEnabled()) {
          $stat = [];
          if ($testMethod !== NULL) {
            $stat['test'] = $testMethod;
          }
          $stat['message'] = $deprecationMessage;
          $stat['ignore-pattern'] = $pattern;
          self::writeStatisticEntry('ignored_by_project', $stat);
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Remove.
   *
   * @todo remove.
   */
  public function metadata(): array {
    if ($env = getenv('DRUPAL_TEST_CONTEXT_KEYS')) {
      $contextKeys = explode(",", $env);
    }
    else {
      $contextKeys = [];
    }

    $tmp = [];
    if ($env = getenv('CI_PIPELINE_ID')) {
      $tmp['CI_PIPELINE_ID'] = $env;
    }
    if ($env = getenv('CI_PIPELINE_NAME')) {
      $tmp['CI_PIPELINE_NAME'] = $env;
    }
    if ($env = getenv('CI_JOB_ID')) {
      $tmp['CI_JOB_ID'] = $env;
    }
    if ($env = getenv('CI_JOB_STAGE')) {
      $tmp['CI_JOB_STAGE'] = $env;
    }
    if ($env = getenv('CI_JOB_NAME')) {
      $tmp['CI_JOB_NAME'] = $env;
    }
    $env = getenv('DRUPAL_PHPUNIT_TESTSUITE');
    if ($env !== FALSE && in_array('phpunit-testsuite', $contextKeys)) {
      $tmp['phpunit_testsuite'] = $env;
    }
    if ($this->phpVersion && in_array('php-version', $contextKeys)) {
      $tmp['php_version'] = $this->phpVersion;
    }
    if ($this->databaseDriver && in_array('database-driver', $contextKeys)) {
      $tmp['database_driver'] = $this->databaseDriver;
    }
    if ($this->databaseVersion && in_array('database-version', $contextKeys)) {
      $tmp['database_version'] = $this->databaseVersion;
    }
    return $tmp;
  }

  public function setCurrentTest(TestMethod $test): void {
    assert(Configuration::collectStatsEnabled(), __METHOD__ . '() must not be called if deprecation statistics collection is not enabled.');

    self::$currentTest['testId'] = $test->id();
    if ($test->metadata()->isIgnoreDeprecations()) {
      self::$currentTest['testIgnoresDeprecations'] = !$test->metadata()->isIgnoreDeprecations()->isEmpty();
    }
    else {
      self::$currentTest['testIgnoresDeprecations'] = FALSE;
    }
  }

  public function deprecationTriggered(DeprecationTriggered|PhpDeprecationTriggered $deprecation): void {
    assert(Configuration::collectStatsEnabled(), __METHOD__ . '() must not be called if deprecation statistics collection is not enabled.');

    if ($deprecation->ignoredByTest() || $deprecation->ignoredByBaseline()) {
      self::$currentTest['ignored_by_phpunit'][] = $deprecation->message();
      return;
    }
    self::$currentTest['reported'][] = $deprecation->message();
  }

  public function testFinished(): void {
    assert(Configuration::collectStatsEnabled(), __METHOD__ . '() must not be called if deprecation statistics collection is not enabled.');

    if (self::$currentTest['testIgnoresDeprecations']) {
      $stat = [];
      $stat['test'] = self::$currentTest['testId'];
      $stat['ignoreDeprecations'] = self::$currentTest['testIgnoresDeprecations'];
      if (isset(self::$currentTest['ignored_by_project'])) {
        $stat['ignoredByProject'] = count(self::$currentTest['ignored_by_project']);
      }
      $stat['ignoredByPhpunit'] = isset(self::$currentTest['ignored_by_phpunit']) ? count(self::$currentTest['ignored_by_phpunit']) : 0;
      if (isset(self::$currentTest['reported'])) {
        $stat['reported'] = count(self::$currentTest['reported']);
      }
      $this->writeStatisticEntry('test_stats', $stat);
    }
    self::$currentTest = [];
  }

  public static function handle(int $errorNumber, string $errorString, string $errorFile, int $errorLine): bool {
    assert(Configuration::projectIgnoresEnabled(), __METHOD__ . '() must not be called if the deprecation handler is not enabled.');

    if (self::isIgnoredByProject(self::$currentTest['testId'] ?? NULL, $errorString)) {
      if (self::$currentTest) {
        self::$currentTest['ignored_by_project'][] = $errorString;
      }
      return TRUE;
    }
    if (self::$currentTest) {
      self::$currentTest['relayed_to_phpunit'][] = $errorString;
    }
    return FALSE;
  }

}
