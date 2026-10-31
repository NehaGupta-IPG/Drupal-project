<?php

namespace Drupal\system\EventSubscriber;

use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Reacts to requests on routes with the _reauthenticate route option.
 */
class ReauthenticateRequestSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RedirectDestinationInterface $redirectDestination,
  ) {}

  /**
   * Redirect for reauthentication as needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $routeMatch = RouteMatch::createFromRequest($request);
    $route = $routeMatch->getRouteObject();
    if (is_null($route) || !$route->hasOption('_reauthenticate') || empty($route->getOption('_reauthenticate'))) {
      return;
    }
    $session = $request->getSession();
    if ($session->has('reauthenticate_timeout') && $session->get('reauthenticate_timeout') >= time()) {
      return;
    }

    $url = Url::fromRoute('system.reauthenticate', [], ['query' => $this->redirectDestination->getAsArray()])->toString();
    $event->setResponse(new RedirectResponse($url));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::REQUEST][] = ['onRequest'];

    return $events;
  }

}
