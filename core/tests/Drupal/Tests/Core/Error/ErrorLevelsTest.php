<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Core\Error\ErrorLevels;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LogLevel;

/**
 * Tests the ErrorLevels class.
 */
#[Group('Error')]
class ErrorLevelsTest extends UnitTestCase {

  /**
   * Tests that all known PHP error constants are mapped.
   */
  #[DataProvider('provideKnownErrorLevels')]
  public function testGetSeverityKnownLevels(int $errorLevel, string $expectedLabel, int $expectedRfcLevel, string $expectedPsrLevel): void {
    $severity = ErrorLevels::getSeverity($errorLevel);
    $this->assertSame($expectedLabel, $severity['label']);
    $this->assertSame($expectedRfcLevel, $severity['rfcLevel']);
    $this->assertSame($expectedPsrLevel, $severity['psrLevel']);
  }

  /**
   * Data provider for known PHP error levels.
   *
   * @return array<string, array{0: int, 1: string, 2: int, 3: string}>
   *   Test cases keyed by error level name.
   */
  public static function provideKnownErrorLevels(): array {
    return [
      'E_ERROR' => [\E_ERROR, 'Error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_WARNING' => [\E_WARNING, 'Warning', RfcLogLevel::WARNING, LogLevel::WARNING],
      'E_PARSE' => [\E_PARSE, 'Parse error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_NOTICE' => [\E_NOTICE, 'Notice', RfcLogLevel::NOTICE, LogLevel::NOTICE],
      'E_CORE_ERROR' => [\E_CORE_ERROR, 'Core error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_CORE_WARNING' => [\E_CORE_WARNING, 'Core warning', RfcLogLevel::WARNING, LogLevel::WARNING],
      'E_COMPILE_ERROR' => [\E_COMPILE_ERROR, 'Compile error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_COMPILE_WARNING' => [\E_COMPILE_WARNING, 'Compile warning', RfcLogLevel::WARNING, LogLevel::WARNING],
      'E_USER_ERROR' => [\E_USER_ERROR, 'User error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_USER_WARNING' => [\E_USER_WARNING, 'User warning', RfcLogLevel::WARNING, LogLevel::WARNING],
      'E_USER_NOTICE' => [\E_USER_NOTICE, 'User notice', RfcLogLevel::NOTICE, LogLevel::NOTICE],
      'E_RECOVERABLE_ERROR' => [\E_RECOVERABLE_ERROR, 'Recoverable fatal error', RfcLogLevel::ERROR, LogLevel::ERROR],
      'E_DEPRECATED' => [\E_DEPRECATED, 'Deprecated function', RfcLogLevel::DEBUG, LogLevel::DEBUG],
      'E_USER_DEPRECATED' => [\E_USER_DEPRECATED, 'User deprecated function', RfcLogLevel::DEBUG, LogLevel::DEBUG],
    ];
  }

  /**
   * Tests that an unknown error level returns the default severity.
   */
  public function testGetSeverityUnknownLevel(): void {
    $severity = ErrorLevels::getSeverity(99999);
    $this->assertSame('Unknown error', $severity['label']);
    $this->assertSame(RfcLogLevel::ERROR, $severity['rfcLevel']);
    $this->assertSame(LogLevel::ERROR, $severity['psrLevel']);
  }

  /**
   * Tests that the LEVELS constant contains all expected PHP error constants.
   */
  public function testLevelsConstantCompleteness(): void {
    $expectedConstants = [
      \E_ERROR,
      \E_WARNING,
      \E_PARSE,
      \E_NOTICE,
      \E_CORE_ERROR,
      \E_CORE_WARNING,
      \E_COMPILE_ERROR,
      \E_COMPILE_WARNING,
      \E_USER_ERROR,
      \E_USER_WARNING,
      \E_USER_NOTICE,
      \E_RECOVERABLE_ERROR,
      \E_DEPRECATED,
      \E_USER_DEPRECATED,
    ];
    foreach ($expectedConstants as $constant) {
      $this->assertArrayHasKey($constant, ErrorLevels::LEVELS);
    }
    $this->assertCount(count($expectedConstants), ErrorLevels::LEVELS);
  }

  /**
   * Tests that each LEVELS entry has all required keys.
   */
  public function testLevelsStructure(): void {
    foreach (ErrorLevels::LEVELS as $errorLevel => $info) {
      $this->assertArrayHasKey('label', $info, "Missing 'label' for error level $errorLevel");
      $this->assertArrayHasKey('rfcLevel', $info, "Missing 'rfcLevel' for error level $errorLevel");
      $this->assertArrayHasKey('psrLevel', $info, "Missing 'psrLevel' for error level $errorLevel");
      $this->assertIsString($info['label']);
      $this->assertIsInt($info['rfcLevel']);
      $this->assertIsString($info['psrLevel']);
    }
  }

  /**
   * Tests getLegacyLevels() returns the format matching drupal_error_levels().
   */
  public function testGetLegacyLevels(): void {
    $this->expectUserDeprecationMessage('Drupal\Core\Error\ErrorLevels::getLegacyLevels() is deprecated in drupal:11.2.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorLevels::getSeverity() or \Drupal\Core\Error\ErrorLevels::LEVELS instead. See https://www.drupal.org/node/3000229');
    $legacy = ErrorLevels::getLegacyLevels();

    // Verify structure: keyed by E_* constant, value is [label, RfcLogLevel].
    $this->assertArrayHasKey(\E_ERROR, $legacy);
    $this->assertSame(['Error', RfcLogLevel::ERROR], $legacy[\E_ERROR]);
    $this->assertSame(['Warning', RfcLogLevel::WARNING], $legacy[\E_WARNING]);
    $this->assertSame(['Deprecated function', RfcLogLevel::DEBUG], $legacy[\E_DEPRECATED]);
    $this->assertCount(count(ErrorLevels::LEVELS), $legacy);
  }

}
