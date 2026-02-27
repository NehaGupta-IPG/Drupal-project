<?php

declare(strict_types=1);

namespace Drupal\TestTools\Extension\DeprecationBridge;

use Drupal\Core\Database\Database;
use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Util\Filesystem;

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
   * Indicates if the extension is enabled.
   */
  private static bool $enabled = FALSE;

  /**
   * A list of deprecation messages that should be ignored if detected.
   *
   * @var list<string>
   */
  private static array $deprecationIgnorePatterns = [];

  /**
   * Directory for files produced by the extension.
   */
  private static string $statisticsArtifactDirectory;

  /**
   * The path to the statistics staging file.
   */
  private static string $statisticsStagingFilePath;

  /**
   * The path to the artifacts base directory.
   *
   * @todo remove.
   */
  private static string $directory;

  /**
   * The PHP major/minor version in use.
   *
   * @todo remove.
   */
  private static string $phpVersion;

  /**
   * The database driver used by the SUT.
   *
   * @todo remove.
   */
  private static ?string $databaseDriver = NULL;

  /**
   * The database version used by the SUT.
   *
   * @todo remove.
   */
  private static ?string $databaseVersion = NULL;

  /**
   * {@inheritdoc}
   */
  public function bootstrap(
    Configuration $configuration,
    Facade $facade,
    ParameterCollection $parameters,
  ): void {
    // Determine staging file path.
    self::$statisticsStagingFilePath = tempnam(sys_get_temp_dir(), 'depsta');

    // Set an environment variable with the configuration, so that isolated
    // tests can access it.
    $config = json_encode([
      'stagingFilePath' => self::$statisticsStagingFilePath,
    ]);
    putenv('_DRUPAL_DEPRECATION_HANDLER_CONFIG=' . $config);

    $facade->registerSubscriber(new TestRunnerFinishedSubscriber());

    // Artifacts directory.
    // $directory = $parameters->get('directory');
    $directory = 'test-artifacts/jobs/' . getenv('CI_JOB_ID');
    Filesystem::createDirectory($directory);
    $realDirectory = realpath($directory);
    if ($realDirectory === FALSE || !is_dir($realDirectory) || !is_writable($realDirectory)) {
      throw new \RuntimeException("Test artifacts directory {$directory} is not a writable directory.");
    }
    self::$directory = $realDirectory;

    // PHP major/minor version.
    preg_match('/^\d+\.\d+/', \PHP_VERSION, $matches);
    self::$phpVersion = $matches[0];

    // Database driver.
    if (!empty(getenv('SIMPLETEST_DB'))) {
      $dbInfo = Database::convertDbUrlToConnectionInfo(getenv('SIMPLETEST_DB'), TRUE);
      self::$databaseDriver = $dbInfo['driver'];
    }

    // Database version.
    if (!empty(getenv('_TARGET_DB'))) {
      self::$databaseVersion = getenv('_TARGET_DB');
    }

    // Set an environment variable with the configuration, so that isolated
    // tests can access it.
    $config = json_encode([
      'directory' => self::$directory,
      'php_version' => self::$phpVersion,
      'database_driver' => self::$databaseDriver,
      'database_version' => self::$databaseVersion,
    ]);
    putenv('_DRUPAL_TEST_ARTIFACT_CONFIG=' . $config);
  }

  /**
   * Returns the extension configuration.
   *
   * For historical reasons, the configuration is stored in the
   * SYMFONY_DEPRECATIONS_HELPER environment variable.
   *
   * @return array|false
   *   An array of configuration variables, of FALSE if the extension is
   *   disabled.
   */
  public static function getConfiguration(): array|FALSE {
    $environmentVariable = getenv('SYMFONY_DEPRECATIONS_HELPER');
    if ($environmentVariable === 'disabled') {
      return FALSE;
    }
    if ($environmentVariable === FALSE) {
      // Ensure ignored deprecation patterns listed in .deprecation-ignore.txt
      // are considered in testing.
      $relativeFilePath = __DIR__ . "/../../../../../.deprecation-ignore.txt";
      $deprecationIgnoreFilename = realpath($relativeFilePath);
      if (empty($deprecationIgnoreFilename)) {
        throw new \InvalidArgumentException(sprintf('The ignoreFile "%s" does not exist.', $relativeFilePath));
      }
      $environmentVariable = "ignoreFile=$deprecationIgnoreFilename";
    }
    parse_str($environmentVariable, $configuration);

    $environmentVariable = getenv('PHPUNIT_FAIL_ON_PHPUNIT_DEPRECATION');
    $phpUnitDeprecationVariable = $environmentVariable !== FALSE ? $environmentVariable : TRUE;
    $configuration['failOnPhpunitDeprecation'] = filter_var($phpUnitDeprecationVariable, \FILTER_VALIDATE_BOOLEAN);

    return $configuration;
  }

  /**
   * Determines if the extension is enabled.
   *
   * @return bool
   *   TRUE if enabled, FALSE if disabled.
   */
  public static function isEnabled(): bool {
    return self::$enabled;
  }

  /**
   * Initializes the extension.
   *
   * @param string|null $ignoreFile
   *   The path to a file containing ignore patterns for deprecations.
   */
  public static function init(?string $ignoreFile = NULL): void {
    if (self::isEnabled()) {
      throw new \LogicException(__CLASS__ . ' is already initialized');
    }

    // Load the deprecation ignore patterns from the specified file.
    if ($ignoreFile && !self::$deprecationIgnorePatterns) {
      if (!is_file($ignoreFile)) {
        throw new \InvalidArgumentException(sprintf('The ignoreFile "%s" does not exist.', $ignoreFile));
      }
      set_error_handler(static function ($t, $m) use ($ignoreFile, &$line): void {
        throw new \RuntimeException(sprintf('Invalid pattern found in "%s" on line "%d"', $ignoreFile, 1 + $line) . substr($m, 12));
      });
      try {
        foreach (file($ignoreFile) as $line => $pattern) {
          if ((trim($pattern)[0] ?? '#') !== '#') {
            preg_match($pattern, '');
            self::$deprecationIgnorePatterns[] = $pattern;
          }
        }
      }
      finally {
        restore_error_handler();
      }
    }

    // Mark the extension as enabled.
    self::$enabled = TRUE;
  }

  /**
   * Dump statistics after test runner end.
   */
  public static function processStatisticsArtifact(): void {
    $content = @file_get_contents(self::$statisticsStagingFilePath);
    $statisticsArtifactDirectory = self::$directory . \DIRECTORY_SEPARATOR . 'deprecation-handler';
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
      'metadata' => self::metadata(),
      'skips' => [],
    ];
    foreach ($encodedStats as $encodedStat) {
      $stat = (array) json_decode(base64_decode($encodedStat));
      if (!empty($stat)) {
        $stats['skips'][$stat['pattern']] = isset($stats['skips'][$stat['pattern']]) ? $stats['skips'][$stat['pattern']] + 1 : 1;
      }
    }

    file_put_contents(
      $statisticsArtifactDirectory . \DIRECTORY_SEPARATOR . $dumpFileName,
      json_encode($stats),
    );
  }

  /**
   * Write a statistic entry.
   */
  private static function writeStatisticEntry(string $testName, string $ignorePattern, string $triggeringMessage): void {
    $artifactEnv = getenv('_DRUPAL_TEST_ARTIFACT_CONFIG');
    $configEnv = getenv('_DRUPAL_DEPRECATION_HANDLER_CONFIG');

    if (empty($artifactEnv) || empty($configEnv)) {
      // If no config available, we cannot record the stats.
      return;
    }

    $artifact = (array) json_decode($artifactEnv);
    $config = (array) json_decode($configEnv);

    $stat = [
      'test' => $testName,
      'pattern' => $ignorePattern,
      'message' => $triggeringMessage,
      'php_version' => $artifact['php_version'],
    ];
    if ($artifact['database_version']) {
      $stat['database'] = $artifact['database_version'];
    }

    file_put_contents(
      $config['stagingFilePath'],
      base64_encode(json_encode($stat)) . "\n",
      \FILE_APPEND,
    );
  }

  /**
   * Determines if an actual deprecation should be ignored.
   *
   * Deprecations that match the patterns included in the ignore file should
   * be ignored.
   *
   * @param \PHPUnit\Framework\TestCase|null $testCase
   *   The test case being executed.
   * @param string $deprecationMessage
   *   The actual deprecation message triggered via trigger_error().
   */
  public static function isIgnoredDeprecation(?TestCase $testCase, string $deprecationMessage): bool {
    if (!self::$deprecationIgnorePatterns) {
      return FALSE;
    }
    foreach (self::$deprecationIgnorePatterns as $pattern) {
      $result = @preg_filter($pattern, '$0', $deprecationMessage);
      if (preg_last_error() !== \PREG_NO_ERROR) {
        throw new \RuntimeException(preg_last_error_msg());
      }
      if ((bool) $result) {
        $testName = $testCase ? $testCase->valueObjectForEvents()->id() : '#not-in-test';
        self::writeStatisticEntry($testName, $pattern, $deprecationMessage);
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
  public static function metadata(): array {
    if ($env = getenv('DRUPAL_TEST_CONTEXT_KEYS')) {
      $contextKeys = explode(",", $env);
    }
    else {
      throw new \RuntimeException('No DRUPAL_TEST_CONTEXT_KEYS environment variable set');
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
    if (self::$phpVersion && in_array('php-version', $contextKeys)) {
      $tmp['php_version'] = self::$phpVersion;
    }
    if (self::$databaseDriver && in_array('database-driver', $contextKeys)) {
      $tmp['database_driver'] = self::$databaseDriver;
    }
    if (self::$databaseVersion && in_array('database-version', $contextKeys)) {
      $tmp['database_version'] = self::$databaseVersion;
    }
    return $tmp;
  }

}
