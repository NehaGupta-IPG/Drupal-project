<?php

declare(strict_types = 1);

namespace Drupal\KernelTests\Core\EventDispatching;

use Drupal\event_test\TestEvent;
use Drupal\event_test\ReusableSubscriber;
use Drupal\event_test_autoconfigure\AutoconfigureSubscriberService;
use Drupal\event_test_autoconfigure\PrioritizingSubscriber;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the dispatching of event to subscribers and listeners.
 *
 * @group Event
 */
class EventDispatchingTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var list<string>
   */
  protected static $modules = ['event_test', 'event_test_autoconfigure'];

  /**
   * Tests a nameless event with subscribers with and without autoconfigure.
   */
  public function testNamelessEvent() {
    $event = new TestEvent();
    $this->getEventDispatcher()->dispatch($event);
    $this->assertSame([
      // A subscriber where the service tag is added explicitly.
      ReusableSubscriber::class . '::testMethod',
      // Multiple subscriber services can exist for the same class.
      '@event_test.test_subscriber_1::testMethod',
      // Adding the tag twice does not result in double subscription.
      '@event_test.test_subscriber_double_tag::testMethod',
      // A subscriber with autoconfigure.
      AutoconfigureSubscriberService::class . '::testMethod',
    ], $event->export());
  }

  /**
   * Tests a named event with ordered subscribers and listeners.
   */
  public function testOrderedEvent() {
    $event = new TestEvent();
    $dispatcher = $this->getEventDispatcher();
    // Add a listener with default priority.
    $dispatcher->addListener('ordered_event', static function (TestEvent $event) {
      $event->report('listener');
    });
    // Add a listener with a priority.
    $dispatcher->addListener('ordered_event', static function (TestEvent $event) {
      $event->report('listener.15');
    }, 15);
    // Verify that listeners for other events are not invoked.
    $dispatcher->addListener('other_event', static function (TestEvent $event) {
      $event->report('listener.other');
    });
    $dispatcher->dispatch($event, 'ordered_event');
    $this->assertSameListsOfStrings([
      // Listeners with highest priority are called first.
      // Callbacks from subscribers are generally called before listeners added
      // at runtime.
      PrioritizingSubscriber::class . '::fiveOrFifteen',
      'listener.15',
      PrioritizingSubscriber::class . '::plusTen',
      // The same method can be subscribed twice, with different weight.
      PrioritizingSubscriber::class . '::fiveOrFifteen',
      PrioritizingSubscriber::class . '::zero',
      'listener',
      AutoconfigureSubscriberService::class . '::minusSeven',
      // The same method can be subscribed twice, with same weight.
      PrioritizingSubscriber::class . '::minusTen',
      PrioritizingSubscriber::class . '::minusTen',
    ], $event->export());
  }

  /**
   * Tests an event with no subscribers.
   */
  public function testDispatchUnknownEvent() {
    $event = new TestEvent();
    $this->getEventDispatcher()->dispatch($event, 'unknown_event');
    $this->assertSame([], $event->export());
  }

  /**
   * Gets the event dispatcher from the container.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   Event dispatcher.
   */
  private function getEventDispatcher(): EventDispatcherInterface {
    return $this->container->get('event_dispatcher');
  }

  /**
   * Asserts that two lists of strings are the same, with simplified format.
   *
   * This gets rid of noise due to numbered indices.
   *
   * @param array $expected
   *   Expected value.
   * @param array $actual
   *   Actual value.
   * @param string $message
   *   Message.
   */
  protected function assertSameListsOfStrings(array $expected, array $actual, string $message = '') {
    $this->assertSame(
      "\n" . implode("\n", $expected) . "\n",
      "\n" . implode("\n", $actual) . "\n",
      $message,
    );
    // Make sure that array keys are as expected.
    $this->assertSame($expected, $actual);
  }

}
