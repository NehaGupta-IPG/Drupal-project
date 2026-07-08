<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Helper class to manage run-tests.sh internal logging.
 *
 * @internal
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
      $this->info('Deprecations are reported. Add the --suppress-deprecations option in the command line to silence.');
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
    if ($this->log === []) {
      return;
    }

    $this->io->writeln("--------------------------------------------------------------------------------");
    $this->io->writeln("Problems detected during the execution of the script");

    $sections = [
      [LogLevel::Info, 'Info:'],
      [LogLevel::Deprecation, 'Deprecation(s):'],
    ];

    foreach ($sections as $section) {
      $tmp = [];
      foreach ($this->log as $entry) {
        if ($entry['level'] === $section[0]) {
          $tmp[] = $entry['message'];
        }
      }
      if (count($tmp)) {
        $this->io->writeln("");
        $this->io->writeln($section[1]);
        foreach ($tmp as $message) {
          $this->io->writeln("* " . wordwrap($message, 78, "\n  "));
        }
      }
    }

    $this->io->writeln("--------------------------------------------------------------------------------");
  }

  /**
   * Returns the internal error status of the runner.
   *
   * @return int
   *   The internal error status of the runner.
   */
  public function internalStatus(): int {
    return $this->hasDeprecations ? \SIMPLETEST_SCRIPT_EXIT_FAILURE : \SIMPLETEST_SCRIPT_EXIT_SUCCESS;
  }

}
