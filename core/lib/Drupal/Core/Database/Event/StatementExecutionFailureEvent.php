<?php

namespace Drupal\Core\Database\Event;

/**
 * Represents the failure of a statement execution as an event.
 */
class StatementExecutionFailureEvent extends StatementExecutionEndEvent {

  public function __construct(
    int $statementObjectId,
    string $key,
    string $target,
    string $queryString,
    array $args,
    array $caller,
    float $startTime,
    public readonly string $exceptionClass,
    public readonly int|string $exceptionCode,
    public readonly string $exceptionMessage,
  ) {
    parent::__construct($statementObjectId, $key, $target, $queryString, $args, $caller, $startTime);
  }

}
