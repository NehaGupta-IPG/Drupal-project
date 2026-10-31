<?php

declare(strict_types = 1);

namespace Drupal\event_test;

/**
 * Event class for testing.
 */
class TestEvent {

  /**
   * Recorded calls.
   *
   * @var list<string>
   */
  private array $recordedCallers = [];

  /**
   * Reports an event subscriber / listener methods.
   *
   * @param string $name
   *   Name identifying the caller.
   */
  public function report(string $name): void {
    $this->recordedCallers[] = $name;
  }

  /**
   * Gets a report of recorded callers.
   *
   * @return list<string>
   *   Recorded caller names.
   */
  public function export(): array {
    return $this->recordedCallers;
  }

}
