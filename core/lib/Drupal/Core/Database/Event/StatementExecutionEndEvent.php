<?php

namespace Drupal\Core\Database\Event;

/**
 * Represents the end of a statement execution as an event.
 */
class StatementExecutionEndEvent extends DatabaseEvent {

  public function __construct(
    public readonly int $statementObjectId,
    public readonly string $key,
    public readonly string $target,
    public readonly string $queryString,
    public readonly array $args,
    public readonly array $caller,
    public readonly float $startTime,
  ) {
    parent::__construct();
  }

  /**
   * Gets the query execution elapsed time.
   *
   * @return float
   *   The elapsed time.
   */
  public function getElapsedTime(): float {
    return $this->time - $this->startTime;
  }

}
