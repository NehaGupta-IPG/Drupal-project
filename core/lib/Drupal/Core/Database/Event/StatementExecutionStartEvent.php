<?php

namespace Drupal\Core\Database\Event;

/**
 * Represents the start of a statement execution as an event.
 */
class StatementExecutionStartEvent extends DatabaseEvent {

  public function __construct(
    public readonly int $statementObjectId,
    public readonly string $key,
    public readonly string $target,
    public readonly string $queryString,
    public readonly array $args,
    public readonly array $caller,
  ) {
    parent::__construct();
  }

}
