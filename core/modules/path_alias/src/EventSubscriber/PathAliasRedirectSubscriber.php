<?php

declare(strict_types=1);

namespace Drupal\path_alias\EventSubscriber;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\path_alias\AliasRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects system paths to their alias.
 */
class PathAliasRedirectSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected AliasRepositoryInterface $aliasRepository,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Redirects system paths to their alias.
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

    // Get the requested path minus the base path.
    $path = $request->getPathInfo();
    if ($path === '/') {
      return;
    }

    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_URL)->getId();
    if ($alias = $this->aliasRepository->lookupBySystemPath(rtrim($path, '/'), $langcode)) {
      if ($qs = $request->getQueryString()) {
        $qs = '?' . $qs;
      }
      $response = new LocalRedirectResponse($request->getUriForPath($alias['alias']) . $qs, 301);
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Use a priority of 33 in order to run before Symfony's router listener.
    // @see \Symfony\Component\HttpKernel\EventListener\RouterListener::getSubscribedEvents()
    $events[KernelEvents::REQUEST][] = ['redirect', 33];
    return $events;
  }

}
