<?php

declare(strict_types = 1);

namespace Drupal\event_test_autoconfigure;

use Drupal\event_test\TestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AutoconfigureSubscriberService implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      // The array keys are event ids or event class names.
      // A possible array value is an array with a single method name and a
      // priority.
      'ordered_event' => ['minusSeven', -7],
      // The priority is optional.
      TestEvent::class => ['testMethod'],
    ];
  }

  public function testMethod(TestEvent $event): void {
    $event->report(__METHOD__);
  }

  public function minusSeven(TestEvent $event): void {
    $event->report(__METHOD__);
  }

}
