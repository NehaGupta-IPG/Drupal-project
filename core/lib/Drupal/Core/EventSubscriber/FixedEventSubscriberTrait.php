<?php

namespace Drupal\Core\EventSubscriber;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adds a fixed event dispatcher.
 *
 * Use this for services where the event subscriber is not yet available.
 * Use
 * @code
 * properties:
 *   eventDispatcherCallable: !service_closure '@event_dispatcher'
 * @endcode
 * for the service definitions using this trait.
 */
trait FixedEventSubscriberTrait {


  /**
   * A callable returning the event dispatcher.
   *
   * This is a closure because injecting the event dispatcher too early
   * breaks it.
   *
   * @var callable
   * @internal
   */
  public $eventDispatcherCallable;

  /**
   * Fixed event dispatcher.
   *
   * Normally the service definition in core.services.yml overrides this but
   * sometimes for eg in the installer a normal container is not yet available
   * and then a minimal event dispatcher with a fixed list of subscribers is
   * used.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $fixedEventDispatcher;

  /**
   * Dispatches an API event via a dispatcher.
   *
   * This method uses the container dispatcher once that's available and the
   * fixed dispatcher until.
   *
   * @template T of \Drupal\Component\EventDispatcher\Event
   *
   * @param T $event
   *   The event.
   * @param string|null $eventName
   *   (Optional) the name of the event to dispatch.
   *
   * @return T
   *   The event.
   */
  protected function dispatchEvent(mixed $event, ?string $eventName = NULL): mixed {
    if (!isset($this->eventDispatcherCallable)) {
      $this->eventDispatcherCallable = fn () => $this->getFixedEventDispatcher();

    }
    return ($this->eventDispatcherCallable)()->dispatch($event, $eventName);
  }

  /**
   * Get the fixed event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher with the subscribers from ::getSubscribers()
   *   registered on it.
   */
  protected function getFixedEventDispatcher(): EventDispatcherInterface {
    if (!isset($this->fixedEventDispatcher)) {
      $this->fixedEventDispatcher = new EventDispatcher();
      array_map([$this->fixedEventDispatcher, 'addSubscriber'], $this->getSubscribers());
    }
    return $this->fixedEventDispatcher;
  }

  /**
   * Subscribers to be registered for early event dispatcher.
   *
   * @return array
   *   A list of subscribers as expected by
   *   \Symfony\Component\EventDispatcher\EventDispatcher\EventDispatcher::addSubscriber().
   */
  abstract protected function getSubscribers(): array;

}
