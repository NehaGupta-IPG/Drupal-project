<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\EventListener;

use Drupal\event_listener_test\EventListenerAnotherTestEvent;
use Drupal\event_listener_test\EventListenerTestEvent;
use Drupal\event_listener_test\EventListenerWeightedTestEvent;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests using AsEventListener attribute to register event listeners.
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class AsEventListenerTest extends KernelTestBase {

  /**
   * Test setting the attribute to register event listeners.
   */
  public function testSubscribedEvents(): void {
    $module_installer = $this->container->get('module_installer');
    $event_dispatcher = $this->container->get('event_dispatcher');
    $registered_listeners = array_keys($event_dispatcher->getListeners());
    // Test listeners are not registered yet.
    static::assertNotContains(EventListenerTestEvent::class, $registered_listeners);
    static::assertNotContains('event_listener.test_event', $registered_listeners);

    // Install test module and clear caches.
    static::assertTrue($module_installer->install(['event_listener_test']));
    drupal_flush_all_caches();

    // Reload event listeners.
    $event_dispatcher = $this->container->get('event_dispatcher');
    $registered_listeners = array_keys($event_dispatcher->getListeners());
    // Test listeners are registered.
    static::assertContains(EventListenerTestEvent::class, $registered_listeners);
    static::assertContains(EventListenerAnotherTestEvent::class, $registered_listeners);
    static::assertContains('event_listener.test_event', $registered_listeners);

    $event = new EventListenerTestEvent();
    $this->container->get('event_dispatcher')->dispatch($event);
    static::assertTrue(isset($GLOBALS['event_listener_named_arguments_1']));
    static::assertTrue(isset($GLOBALS['event_listener_named_arguments_2']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_3']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_4']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_5']));

    // Dispatch second event.
    $this->container->get('event_dispatcher')->dispatch($event, 'event_listener.test_event');
    static::assertTrue(isset($GLOBALS['event_listener_named_arguments_3']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_4']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_5']));

    $event = new EventListenerAnotherTestEvent();
    $this->container->get('event_dispatcher')->dispatch($event);
    static::assertTrue(isset($GLOBALS['event_listener_named_arguments_4']));
    static::assertFalse(isset($GLOBALS['event_listener_named_arguments_5']));

    $event = new EventListenerWeightedTestEvent();
    $this->container->get('event_dispatcher')->dispatch($event);
    static::assertTrue(isset($GLOBALS['event_listener_named_arguments_5']));
    static::assertEquals('event_listener_named_arguments_6', $GLOBALS['event_listener_named_arguments_5']);
  }

}
