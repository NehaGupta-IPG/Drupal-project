<?php

declare(strict_types=1);

namespace Drupal\session_test\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Defines a test session subscriber that checks whether the session is empty.
 */
class SessionTestSubscriber implements EventSubscriberInterface {

  /**
   * Performs tasks for session_test module on kernel.response.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Event to process.
   */
  public function onKernelResponseSessionTest(ResponseEvent $event): void {
    // Set header for session testing.
    $response = $event->getResponse();
    $request = $event->getRequest();
    $response->headers->set('X-Session-Empty', $request->hasPreviousSession() ? '0' : '1');
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onKernelResponseSessionTest'];
    return $events;
  }

}
