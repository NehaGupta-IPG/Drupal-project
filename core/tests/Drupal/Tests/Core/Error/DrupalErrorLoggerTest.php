<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Core\Error\DrupalErrorLogger;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

/**
 * Tests the DrupalErrorLogger class.
 */
#[Group('Error')]
class DrupalErrorLoggerTest extends UnitTestCase {

  /**
   * Tests logging with a Drupal logger available.
   */
  public function testLogWithDrupalLogger(): void {
    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log')
      ->with(
        $this->identicalTo(RfcLogLevel::ERROR),
        $this->identicalTo(Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string'),
        $this->callback(function (array $context): bool {
          return $context['%type'] === 'Error'
            && $context['@message'] === 'Test error message'
            && $context['%function'] === 'testFunction()'
            && $context['%file'] === 'test.php'
            && $context['%line'] === 42
            && $context['severity_level'] === RfcLogLevel::ERROR;
        }),
      );

    $logger = new DrupalErrorLogger($drupalLogger);
    $logger->log(RfcLogLevel::ERROR, 'Test error message', [
      'type_label' => 'Error',
      'function' => 'testFunction()',
      'file' => 'test.php',
      'line' => 42,
      'severity_level' => RfcLogLevel::ERROR,
    ]);
  }

  /**
   * Tests logging without a Drupal logger (early boot).
   */
  public function testLogWithoutDrupalLogger(): void {
    // Should not throw when no Drupal logger is available.
    $logger = new DrupalErrorLogger();
    $logger->log(RfcLogLevel::ERROR, 'Test error', [
      'type_label' => 'Error',
      'severity_level' => RfcLogLevel::ERROR,
    ]);

    // If we got here without an exception, the test passes.
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that setDrupalLogger upgrades the logger.
   */
  public function testSetDrupalLogger(): void {
    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log');

    $logger = new DrupalErrorLogger();
    $logger->setDrupalLogger($drupalLogger);
    $logger->log(RfcLogLevel::WARNING, 'Upgraded', [
      'type_label' => 'Warning',
      'severity_level' => RfcLogLevel::WARNING,
    ]);
  }

  /**
   * Tests that exception context is decoded using Error::decodeException().
   */
  public function testLogWithExceptionContext(): void {
    $exception = new \RuntimeException('Exception message');
    $decoded = Error::decodeException($exception);

    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log')
      ->with(
        $this->identicalTo(RfcLogLevel::ERROR),
        $this->anything(),
        $this->callback(function (array $context) use ($decoded): bool {
          return $context['%type'] === $decoded['%type']
            && $context['@message'] === $decoded['@message'];
        }),
      );

    $logger = new DrupalErrorLogger($drupalLogger);
    $logger->log(RfcLogLevel::ERROR, 'Exception', [
      'exception' => $exception,
      'severity_level' => RfcLogLevel::ERROR,
    ]);
  }

  /**
   * Tests that logger failure falls back to error_log.
   */
  public function testLogFallbackOnLoggerFailure(): void {
    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log')
      ->willThrowException(new \RuntimeException('Database gone'));

    $logger = new DrupalErrorLogger($drupalLogger);
    $this->expectOutputRegex('/Failed to log error/');

    // Should not throw — falls back to error_log().
    $logger->log(RfcLogLevel::ERROR, 'Test error', [
      'type_label' => 'Error',
      'function' => 'test()',
      'file' => 'test.php',
      'line' => 1,
      'severity_level' => RfcLogLevel::ERROR,
    ]);
    $this->addToAssertionCount(1);
  }

  /**
   * Tests that default severity is derived from ErrorLevels when not provided.
   */
  public function testDefaultSeverityFromErrorLevels(): void {
    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log')
      ->with(
        // E_WARNING maps to RfcLogLevel::WARNING.
        $this->identicalTo(RfcLogLevel::WARNING),
        $this->anything(),
        $this->anything(),
      );

    $logger = new DrupalErrorLogger($drupalLogger);
    // Pass 'type' (PHP error constant) without 'severity_level'.
    $logger->log(RfcLogLevel::WARNING, 'A warning', [
      'type' => \E_WARNING,
      'type_label' => 'Warning',
    ]);
  }

  /**
   * Tests buildErrorArray provides defaults for missing context keys.
   */
  public function testBuildErrorArrayDefaults(): void {
    $drupalLogger = $this->createMock(LoggerInterface::class);
    $drupalLogger->expects($this->once())
      ->method('log')
      ->with(
        $this->anything(),
        $this->anything(),
        $this->callback(function (array $context): bool {
          return $context['%function'] === 'unknown'
            && $context['%file'] === 'unknown'
            && $context['%line'] === 0;
        }),
      );

    $logger = new DrupalErrorLogger($drupalLogger);
    // Minimal context — no function/file/line.
    $logger->log(RfcLogLevel::ERROR, 'Minimal', [
      'severity_level' => RfcLogLevel::ERROR,
    ]);
  }

}
