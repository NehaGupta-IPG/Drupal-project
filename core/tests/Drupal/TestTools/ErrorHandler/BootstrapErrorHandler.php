<?php

declare(strict_types=1);

namespace Drupal\TestTools\ErrorHandler;

use Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler;
use PHPUnit\Event\Code\NoTestCaseObjectOnCallStackException;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Runner\ErrorHandler as PhpUnitErrorHandler;

/**
 * Drupal's PHPUnit base error handler.
 *
 * This code works in coordination with DeprecationHandler.
 *
 * This error handler is registered during PHPUnit's runner bootstrap, and
 * overrides PHPUnit's own error handler.
 *
 * @see \Drupal\TestTools\Extension\DeprecationBridge\DeprecationHandler
 *
 * @internal
 */
final class BootstrapErrorHandler {

  /**
   * Statistics for the currently executing test case.
   *
   * @see \Drupal\TestTools\Extension\DeprecationBridge\TestPreparationStartedSubscriber
   * @see \Drupal\TestTools\Extension\DeprecationBridge\TestFinishedSubscriber
   */
  private static ?array $currentTest = NULL;

  /**
   * @param \PHPUnit\Runner\ErrorHandler $phpUnitErrorHandler
   *   An instance of PHPUnit's runner own error handler. Any error not
   *   managed here will fall back to it.
   */
  public function __construct(
    private readonly PhpUnitErrorHandler $phpUnitErrorHandler,
  ) {
  }

  public static function setCurrentTest(TestMethod $test): void {
    self::$currentTest['testId'] = $test->id();
    if ($test->metadata()->isIgnoreDeprecations()) {
      self::$currentTest['testIgnoresDeprecations'] = !$test->metadata()->isIgnoreDeprecations()->isEmpty();
    }
    else {
      self::$currentTest['testIgnoresDeprecations'] = FALSE;
    }
  }

  public static function deprecationTriggered(DeprecationTriggered $deprecation): void {
    if ($deprecation->ignoredByTest() || $deprecation->ignoredByBaseline()) {
      self::$currentTest['ignored_by_phpunit'][] = $deprecation->message();
      return;
    }
    self::$currentTest['reported'][] = $deprecation->message();
  }

  public static function testFinished(): void {
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
      DeprecationHandler::writeStatisticEntry('test_stats', $stat);
    }
    self::$currentTest = [];
  }

  /**
   * Executes when the object is called as a function.
   *
   * @param int $errorNumber
   *   The level of the error raised.
   * @param string $errorString
   *   The error message.
   * @param string $errorFile
   *   The filename that the error was raised in.
   * @param int $errorLine
   *   The line number the error was raised at.
   *
   * @return bool
   *   TRUE to stop error handling, FALSE to let the normal error handler
   *   continue.
   */
  public function __invoke(int $errorNumber, string $errorString, string $errorFile, int $errorLine): bool {
    if (!DeprecationHandler::projectIgnoresEnabled()) {
      throw new \RuntimeException(__METHOD__ . '() must not be called if the deprecation handler is not enabled.');
    }

    // If the deprecation handled is one of those in the ignore list, we keep
    // running.
    if (E_USER_DEPRECATED === $errorNumber || E_DEPRECATED === $errorNumber) {
      if (DeprecationHandler::isIgnoredByProject(self::$currentTest['testId'] ?? NULL, $errorString)) {
        if (self::$currentTest) {
          self::$currentTest['ignored_by_project'][] = $errorString;
        }
        return TRUE;
      }
      if (self::$currentTest) {
        self::$currentTest['relayed_to_phpunit'][] = $errorString;
      }
    }

    // In all other cases (errors, warnings, deprecations to be reported), we
    // fall back to PHPUnit's error handler, an instance of which was created
    // when this error handler was created.
    try {
      call_user_func($this->phpUnitErrorHandler, $errorNumber, $errorString, $errorFile, $errorLine);
    }
    catch (NoTestCaseObjectOnCallStackException) {
      // If we end up here, it's likely because a test's processing has
      // finished already and we are processing an error that occurred while
      // dealing with STDOUT rewinding or truncating. Do nothing.
    }
    return TRUE;
  }

}
