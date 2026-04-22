<?php

namespace Drupal\event_listener_test\EventListener;

use Drupal\event_listener_test\EventListenerAnotherTestEvent;
use Drupal\event_listener_test\EventListenerTestEvent;
use Drupal\event_listener_test\EventListenerWeightedTestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Registers various event listeners using AsEventListener attribute.
 */
#[AsEventListener]
#[AsEventListener(
  event: EventListenerAnotherTestEvent::class,
)]
class TestAsEventListenerListener {

  /**
   * Default invocation method.
   *
   * @param \Drupal\event_listener_test\EventListenerTestEvent $event
   *   Event.
   */
  public function __invoke(EventListenerTestEvent $event): void {
    // Set a global value we can check in test code.
    $GLOBALS['event_listener_named_arguments_1'] = 'event_listener_named_arguments_1';
  }

  /**
   * Test subscribing to the EventListenerTestEvent.
   *
   * @param \Drupal\event_listener_test\EventListenerTestEvent $event
   *   Event.
   */
  #[AsEventListener]
  public function onEventListenerNamedArgument(EventListenerTestEvent $event): void {
    // Set a global value we can check in test code.
    $GLOBALS['event_listener_named_arguments_2'] = 'event_listener_named_arguments_2';
  }

  /**
   * Test subscribing using specific event name.
   *
   * @param \Drupal\event_listener_test\EventListenerTestEvent $event
   *   Event.
   */
  #[AsEventListener(event: 'event_listener.test_event')]
  public static function onEventListenerTestEvent(EventListenerTestEvent $event): void {
    // Set a global value we can check in test code.
    $GLOBALS['event_listener_named_arguments_3'] = 'event_listener_named_arguments_3';
  }

  /**
   * Test subscribing to EventListenerAnotherTestEvent using without method.
   *
   * @param \Drupal\event_listener_test\EventListenerAnotherTestEvent $event
   *   Event.
   */
  public function onDrupalEventListenerTestEventListenerAnotherTestEvent(EventListenerAnotherTestEvent $event): void {
    // Set a global value we can check in test code.
    $GLOBALS['event_listener_named_arguments_4'] = 'event_listener_named_arguments_4';
  }

  /**
   * Test subscribing to an event with priority.
   *
   * @param \Drupal\event_listener_test\EventListenerWeightedTestEvent $event
   *   Event.
   */
  #[AsEventListener(EventListenerWeightedTestEvent::class)]
  public function onWeightedEventListenerTestEvent(EventListenerWeightedTestEvent $event): void {
    // Set a global value we can check in test code.
    $GLOBALS['event_listener_named_arguments_5'] = 'event_listener_named_arguments_6';
  }

  /**
   * Test subscribing to an event with priority.
   *
   * This listener should be called before onWeightedEventListenerTestEvent().
   *
   * @param \Drupal\event_listener_test\EventListenerWeightedTestEvent $event
   *   Event.
   */
  #[AsEventListener(
    event: EventListenerWeightedTestEvent::class,
    priority: 1,
  )]
  public function onWeightedEventListenerTestEventPriority(EventListenerWeightedTestEvent $event): void {
    // Override global variable.
    $GLOBALS['event_listener_named_arguments_5'] = 'event_listener_named_arguments_5';
  }

}
