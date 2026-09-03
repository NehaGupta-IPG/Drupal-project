<?php

declare(strict_types=1);

namespace Drupal\Tests;

use Drupal\Core\Database\DatabaseExceptionBase;
use Drupal\Core\Database\Utility\SqlDumper;
use Drupal\TestTools\ErrorHandler\BootstrapErrorHandler;
use Drupal\TestTools\Extension\DeprecationBridge\Configuration as DeprecationHandlerConfiguration;
use Drupal\TestTools\Extension\Dump\DebugDump;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\TextUI\Configuration\Registry as ConfigurationRegistry;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Provides methods common across Drupal test classes.
 *
 * This trait is imported in the DrupalTestCase class, that is the common
 * ancestor class for unit, kernel, functional, functional javascript and
 * build base test classes.
 *
 * Normally, you do not need to import this trait explicitly.
 *
 * The trait may need to be imported only in tests classes that directly extend
 * \PHPUnit\Framework\TestCase, like for example Component unit tests, that
 * need to be executed with the Drupal testing framework (typically, using
 * GitLabCI).
 *
 * @see \Drupal\Tests\DrupalTestCase
 * @see \Drupal\Tests\UnitTestCase
 * @see \Drupal\KernelTests\KernelTestBase
 * @see \Drupal\Tests\BrowserTestBase
 * @see \Drupal\BuildTests\Framework\BuildTestBase
 */
trait DrupalTestCaseTrait {

  /**
   * Registers the dumper CLI handler when the DebugDump extension is enabled.
   */
  #[BeforeClass]
  public static function setDebugDumpHandler(): void {
    if (DebugDump::isEnabled()) {
      VarDumper::setHandler(DebugDump::class . '::cliHandler');
    }
  }

  /**
   * Checks legacy SYMFONY_DEPRECATIONS_HELPER env variable is not used.
   */
  #[Before]
  public function checkLegacySymfonyDeprecationHelperEnvVariable(): void {
    if (getenv('SYMFONY_DEPRECATIONS_HELPER') !== FALSE) {
      @trigger_error("Using the SYMFONY_DEPRECATIONS_HELPER environment variable to configure test runs is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. See https://www.drupal.org/node/3594014", E_USER_DEPRECATED);
    }
  }

  /**
   * Checks the test error handler after test execution.
   */
  #[After]
  public function checkErrorHandlerOnTearDown(): void {
    // We expect that the current error handler is the one set during the
    // PHPUnit bootstrap. If not, the error handler was changed during the test
    // execution but not properly restored during ::tearDown().
    if (DeprecationHandlerConfiguration::instance()->projectIgnoresEnabled && !get_error_handler() instanceof BootstrapErrorHandler) {
      throw new \RuntimeException(sprintf('%s registered its own error handler without restoring the previous one before or during tear down. This can cause unpredictable test results. Ensure the test cleans up after itself.', $this->name()));
    }
  }

  /**
   * Expects an exactly matching exception message.
   *
   * Forward compatibility for PHPUnit 13.
   *
   * @param string $message
   *   The expected exception message.
   */
  protected function expectExceptionMessageIs(string $message): void {
    $this->expectExceptionMessage($message);
  }

  /**
   * Expects an exception message containing a specified string.
   *
   * Forward compatibility for PHPUnit 13.
   *
   * @param string $message
   *   The expected exception message.
   */
  protected function expectExceptionMessageIsOrContains(string $message): void {
    $this->expectExceptionMessage($message);
  }

  /**
   * Implements PHPUnit hook to alter a throwable before reporting.
   *
   * Database exceptions may carry the information of the executed statement,
   * that can be exported to a more readable dump.
   *
   * @param \Throwable $t
   *   The throwable to be transformed.
   *
   * @return \Throwable
   *   A transformed throwable.
   */
  protected function transformException(\Throwable $t): \Throwable {
    if ($t instanceof DatabaseExceptionBase && $t->info !== NULL) {
      $infoDump = (new SqlDumper())->export($t->info, self::testRunnerUsesColors());
      if ($infoDump !== '') {
        // The dump is appended to the message of the throwable itself, instead
        // of returning a new one. A new throwable would carry this method's own
        // ::getFile(), ::getLine() and ::getTrace(), so PHPUnit would report
        // the failure as happening here instead of in the code that executed
        // the statement.
        (function () use ($infoDump): void {
          $this->message .= "\n" . $infoDump;
        })->call($t);
      }
    }
    return $t;
  }

  /**
   * Determines whether the test runner is printing colored output.
   *
   * @return bool
   *   TRUE if colors are enabled for the current test run, FALSE otherwise.
   */
  private static function testRunnerUsesColors(): bool {
    // This is the resolved value of the --colors option, and it is restored in
    // the child processes of tests running in process isolation too. Without
    // it the dump would default to colored output, and the ANSI sequences would
    // end up in the JUnit log, and therefore in run-tests.sh output. PHPUnit
    // marks the registry as internal, so a failure to read it must not be
    // fatal: the dump is only a debugging aid.
    try {
      return ConfigurationRegistry::get()->colors();
    }
    catch (\Throwable) {
      return FALSE;
    }
  }

}
