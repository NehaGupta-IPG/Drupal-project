<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Event;

use Drupal\Core\Test\TestRun;

/**
 * Represents the end of a test execution.
 *
 * @internal
 */
final class TestExecutionFinished {

  /**
   * The time of the poll event.
   */
  public readonly float $time;

  /**
   * The amount of memory currently in use.
   */
  public readonly int $memory;

  /**
   * Constructor.
   *
   * @param int $cycle
   *   The count of completed cycles in the main loop.
   * @param \Drupal\Core\Test\TestRun $testRun
   *   The test run.
   */
  public function __construct(
    public readonly int $cycle,
    public readonly TestRun $testRun,
  ) {
    $this->time = microtime(TRUE);
    $this->memory = memory_get_usage(TRUE);
  }

}
