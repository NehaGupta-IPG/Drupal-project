<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Event;

/**
 * Represents a status poll of the main runner during test execution.
 *
 * @internal
 */
final class RunnerTestsExecutionPoll extends EventBase {

  /**
   * Constructor.
   *
   * @param int $cycle
   *   The count of completed cycles in the main loop.
   * @param int $launched
   *   The number of tests launched so far.
   * @param int $terminated
   *   The number of tests completed so far.
   * @param int $active
   *   The number of tests currently being processed.
   */
  public function __construct(
    public readonly int $cycle,
    public readonly int $launched,
    public readonly int $terminated,
    public readonly int $active,
  ) {
    parent::__construct();
  }

}
