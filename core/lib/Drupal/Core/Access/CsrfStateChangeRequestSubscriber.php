<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Route;

/**
 * Marks unprotected GET requests before their controller is invoked.
 *
 * @internal
 */
class CsrfStateChangeRequestSubscriber implements EventSubscriberInterface {

  /**
   * Marks an unprotected GET request.
   */
  public function onController(ControllerEvent $event): void {
    if (!$event->isMainRequest() || !$event->getRequest()->isMethod('GET')) {
      return;
    }

    $route = $event->getRequest()->attributes->get(RouteObjectInterface::ROUTE_OBJECT);
    if ($route instanceof Route && $route->getRequirement('_csrf_token') !== 'TRUE') {
      $event->getRequest()->attributes->set('_csrf_state_change_guard', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::CONTROLLER => 'onController'];
  }

}
