<?php

namespace Drupal\Core\EventSubscriber;

use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resets services tagged with kernel.reset after each request.
 *
 * This ensures per-request state does not persist into subsequent requests on
 * persistent PHP runtimes and kernel tests.
 */
class ServiceResetTerminateSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ServicesResetterInterface $servicesResetter,
  ) {}

  /**
   * Resets resettable services after the response has been sent.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate event.
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    $this->servicesResetter->reset();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use a low priority so this runs after all other terminate subscribers.
    return [KernelEvents::TERMINATE => ['onKernelTerminate', -1024]];
  }

}
