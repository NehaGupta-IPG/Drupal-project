<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Helper class to manage run-tests.sh internal logging.
 */
final class Logger {

  /**
   * The log.
   *
   * @var list<array{'level': \Drupal\TestTools\TestRunner\LogLevel, 'message': string}>
   */
  private array $log = [];

  /**
   * Indicates if at least a deprecation was reported.
   */
  private bool $hasDeprecations = FALSE;

  public function __construct(
    private ConsoleOutput $io,
  ) {
  }

  /**
   * Logs an information.
   *
   * @param string $message
   *   The message.
   */
  public function info(string $message): void {
    $this->log[] = [
      'level' => LogLevel::Info,
      'message' => $message,
    ];
  }

  /**
   * Logs a deprecation.
   *
   * @param string $message
   *   The message.
   */
  public function deprecation(string $message): void {
    if (!$this->hasDeprecations) {
      $this->info('Deprecations are reported. Add the --suppress-deprecations command line to silence.');
      $this->hasDeprecations = TRUE;
    }
    $this->log[] = [
      'level' => LogLevel::Deprecation,
      'message' => $message,
    ];
  }

  /**
   * Prints the log.
   */
  public function print(): void {
    dump($this->log);
  }

}
