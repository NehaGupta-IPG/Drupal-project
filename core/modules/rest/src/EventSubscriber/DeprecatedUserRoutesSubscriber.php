<?php

namespace Drupal\rest\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Replace the deprecated user module routes with our own.
 */
class DeprecatedUserRoutesSubscriber implements EventSubscriberInterface {

  /**
   * Copies altered deprecated user module routes to the REST module routes.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The route build event.
   */
  public function onRouteAlter(RouteBuildEvent $event): void {
    foreach (['pass', 'login', 'login_status', 'logout'] as $route_name) {
      $user_route = $event->getRouteCollection()->get("user.$route_name.http");
      $rest_route = $event->getRouteCollection()->get("rest.$route_name");
      if ($user_route && $rest_route) {
        $controller = $rest_route->getDefault('_controller');
        $rest_route = clone $user_route;
        $rest_route->setDefault('_controller', $controller);
        $options = $rest_route->getOptions();
        unset($options['deprecated']);
        $rest_route->setOptions($options);
        $event->getRouteCollection()->add("rest.$route_name", $rest_route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after route subscribers with the default priority.
    $events[RoutingEvents::ALTER] = ['onRouteAlter', -1000];
    return $events;
  }

}
