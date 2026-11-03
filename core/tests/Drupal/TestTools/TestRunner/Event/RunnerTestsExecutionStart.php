<?php

declare(strict_types=1);

namespace Drupal\TestTools\TestRunner\Event;

/**
 * Represents the start of the tests execution.
 *
 * @internal
 */
final class RunnerTestsExecutionStart extends EventBase {

  /**
   * Constructor.
   *
   * @param list<string> $testsToRun
   *   The tests to run.
   */
  public function __construct(
    public readonly array $testsToRun,
  ) {
    parent::__construct();
  }

}
