<?php

declare(strict_types = 1);

namespace Drupal\event_test;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReusableSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ?string $id = NULL,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      // A subscriber can return a single method name for an event.
      TestEvent::class => 'testMethod',
    ];
  }

  public function testMethod(TestEvent $event): void {
    if ($this->id !== NULL) {
      $event->report('@' . $this->id . '::' . __FUNCTION__);
    }
    else {
      $event->report(__METHOD__);
    }
  }

}
