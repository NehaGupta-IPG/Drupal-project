<?php

declare(strict_types=1);

namespace Drupal\Core\Error;

use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LogLevel;

/**
 * Maps PHP error constants to Drupal and PSR-3 severity levels.
 *
 * This class replaces the procedural drupal_error_levels() function and
 * bridges three severity systems:
 * - PHP error constants (E_ERROR, E_WARNING, etc.)
 * - Drupal's RfcLogLevel (RFC 5424 integer severity)
 * - PSR-3 LogLevel (string severity used by Symfony ErrorHandler)
 *
 * @see \Drupal\Core\Logger\RfcLogLevel
 * @see \Psr\Log\LogLevel
 */
final class ErrorLevels {

  /**
   * Map of PHP error constants to severity information.
   *
   * Each entry maps a PHP error constant to an array containing:
   * - label: Human-readable error type label.
   * - rfcLevel: Drupal's RfcLogLevel constant (integer).
   * - psrLevel: PSR-3 LogLevel constant (string).
   *
   * @var array<int, array{label: string, rfcLevel: int, psrLevel: string}>
   */
  public const LEVELS = [
    \E_ERROR => [
      'label' => 'Error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_WARNING => [
      'label' => 'Warning',
      'rfcLevel' => RfcLogLevel::WARNING,
      'psrLevel' => LogLevel::WARNING,
    ],
    \E_PARSE => [
      'label' => 'Parse error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_NOTICE => [
      'label' => 'Notice',
      'rfcLevel' => RfcLogLevel::NOTICE,
      'psrLevel' => LogLevel::NOTICE,
    ],
    \E_CORE_ERROR => [
      'label' => 'Core error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_CORE_WARNING => [
      'label' => 'Core warning',
      'rfcLevel' => RfcLogLevel::WARNING,
      'psrLevel' => LogLevel::WARNING,
    ],
    \E_COMPILE_ERROR => [
      'label' => 'Compile error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_COMPILE_WARNING => [
      'label' => 'Compile warning',
      'rfcLevel' => RfcLogLevel::WARNING,
      'psrLevel' => LogLevel::WARNING,
    ],
    \E_USER_ERROR => [
      'label' => 'User error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_USER_WARNING => [
      'label' => 'User warning',
      'rfcLevel' => RfcLogLevel::WARNING,
      'psrLevel' => LogLevel::WARNING,
    ],
    \E_USER_NOTICE => [
      'label' => 'User notice',
      'rfcLevel' => RfcLogLevel::NOTICE,
      'psrLevel' => LogLevel::NOTICE,
    ],
    \E_RECOVERABLE_ERROR => [
      'label' => 'Recoverable fatal error',
      'rfcLevel' => RfcLogLevel::ERROR,
      'psrLevel' => LogLevel::ERROR,
    ],
    \E_DEPRECATED => [
      'label' => 'Deprecated function',
      'rfcLevel' => RfcLogLevel::DEBUG,
      'psrLevel' => LogLevel::DEBUG,
    ],
    \E_USER_DEPRECATED => [
      'label' => 'User deprecated function',
      'rfcLevel' => RfcLogLevel::DEBUG,
      'psrLevel' => LogLevel::DEBUG,
    ],
  ];

  /**
   * Default severity for unknown PHP error levels.
   *
   * @var array{label: string, rfcLevel: int, psrLevel: string}
   */
  private const DEFAULT_SEVERITY = [
    'label' => 'Unknown error',
    'rfcLevel' => RfcLogLevel::ERROR,
    'psrLevel' => LogLevel::ERROR,
  ];

  /**
   * Returns severity information for a PHP error level.
   *
   * @param int $errorLevel
   *   A PHP error constant (e.g. E_ERROR, E_WARNING).
   *
   * @return array{label: string, rfcLevel: int, psrLevel: string}
   *   An array with 'label', 'rfcLevel', and 'psrLevel' keys.
   */
  public static function getSeverity(int $errorLevel): array {
    return self::LEVELS[$errorLevel] ?? self::DEFAULT_SEVERITY;
  }

  /**
   * Returns the legacy-format levels array for backward compatibility.
   *
   * This method returns data in the same format as the deprecated
   * drupal_error_levels() function: an array keyed by PHP error constant
   * with values of [label, RfcLogLevel].
   *
   * @return array<int, array{0: string, 1: int}>
   *   The error levels in legacy format.
   *
   * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
   *   \Drupal\Core\Error\ErrorLevels::getSeverity() or
   *   \Drupal\Core\Error\ErrorLevels::LEVELS instead.
   * @see https://www.drupal.org/node/3000229
   */
  public static function getLegacyLevels(): array {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\ErrorLevels::getSeverity() or \Drupal\Core\Error\ErrorLevels::LEVELS instead. See https://www.drupal.org/node/3000229', E_USER_DEPRECATED);
    $levels = [];
    foreach (self::LEVELS as $errorLevel => $info) {
      $levels[$errorLevel] = [$info['label'], $info['rfcLevel']];
    }
    return $levels;
  }

}
