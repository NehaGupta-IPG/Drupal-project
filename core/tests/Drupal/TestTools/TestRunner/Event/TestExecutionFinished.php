<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Event;

use Drupal\Core\Test\TestRun;

/**
 * Represents the end of a test execution.
 *
 * @internal
 */
final class TestExecutionFinished extends EventBase {

  /**
   * Constructor.
   *
   * @param int $cycle
   *   The count of completed cycles in the main loop.
   * @param \Drupal\Core\Test\TestRun $testRun
   *   The test run.
   * @param array $result
   *   The test run results.
   */
  public function __construct(
    public readonly int $cycle,
    public readonly TestRun $testRun,
    public readonly array $result,
  ) {
    parent::__construct();
  }

}
