<?php

declare(strict_types=1);

namespace Drupal\hook_collector_hook_attribute\EventSubscriber;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Test\Hook\TrackHookInvocationsTrait;
use Drupal\hook_collector_hook_attribute\Event\TestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Example of an event subscriber that also has a hook implementation.
 *
 * This is used to test that an event subscribers handlers will not trigger
 * more than once after HookCollectorPass processes them for hooks.
 */
class EventSubscriberWithHook implements EventSubscriberInterface {

  use TrackHookInvocationsTrait;

  public function __construct(public readonly string $notAutowiredArgument) {}

  /**
   * Handles the event.
   *
   * @param \Drupal\hook_collector_hook_attribute\Event\TestEvent $event
   *   The event.
   */
  public function onEvent(TestEvent $event): void {
    $invocations = $event->getInvocations();
    $this->trackInvocation($invocations, __FUNCTION__);
    $event->setInvocations($invocations);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [TestEvent::class => 'onEvent'];
  }

  /**
   * Implements hook_test_hook_1().
   */
  #[Hook('test_hook_1')]
  public function testHook1(array &$invocations): void {
    $this->trackInvocation($invocations, __FUNCTION__);
    if ($this->notAutowiredArgument !== 'not_autowired') {
      throw new \InvalidArgumentException('Non-autowired property not set');
    }
  }

}
