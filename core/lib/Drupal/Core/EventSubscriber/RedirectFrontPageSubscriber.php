<?php

declare(strict_types=1);

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects the system path configured as the front page to '/'.
 */
class RedirectFrontPageSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ConfigFactoryInterface $config,
  ) {}

  /**
   * Redirects the system path configured as the front page to '/'.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The RequestEvent to process.
   */
  public function redirect(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$event->isMainRequest()) {
      return;
    }
    if ($request->getRequestFormat() !== 'html') {
      return;
    }

    $config = $this->config->get('system.site');
    // Get the requested path minus the base path.
    $path = $request->getPathInfo();

    if (rtrim($path, '/') === $config->get('page.front')) {
      if ($qs = $request->getQueryString()) {
        $qs = '?' . $qs;
      }
      $response = new LocalRedirectResponse($request->getUriForPath('/') . $qs, 301);
      $response->addCacheableDependency($config);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use a lower priority than RedirectLeadingSlashesSubscriber.
    $events[KernelEvents::REQUEST][] = ['redirect', 990];
    return $events;
  }

}
