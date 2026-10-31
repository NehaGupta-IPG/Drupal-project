<?php

declare(strict_types = 1);

namespace Drupal\event_test_autoconfigure;

use Drupal\event_test\TestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PrioritizingSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      // A possible array value is a list of methods with priorities.
      'ordered_event' => [
        ['minusTen', -10],
        ['plusTen', 10],
        // Each item must be an array, but the priority is optional.
        ['zero'],
        // The same method can be subscribed twice, with same weight.
        ['minusTen', -10],
        // The same method can be subscribed twice, with different weight.
        ['fiveOrFifteen', 5],
        ['fiveOrFifteen', 15],
      ],
    ];
  }

  public function zero(TestEvent $event) {
    $event->report(__METHOD__);
  }

  public function minusTen(TestEvent $event) {
    $event->report(__METHOD__);
  }

  public function plusTen(TestEvent $event) {
    $event->report(__METHOD__);
  }

  public function fiveOrFifteen(TestEvent $event) {
    $event->report(__METHOD__);
  }

}
