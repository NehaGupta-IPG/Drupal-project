<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * A test event class that can be displatched.
 */
class TestEvent extends Event {

  public function __construct(protected array $invocations) {}

  public function getInvocations(): array {
    return $this->invocations;
  }

  public function setInvocations(array $invocations): self {
    $this->invocations = $invocations;
    return $this;
  }

}
