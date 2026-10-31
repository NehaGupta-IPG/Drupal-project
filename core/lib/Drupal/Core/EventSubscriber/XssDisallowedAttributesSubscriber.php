<?php

declare(strict_types=1);

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the additional attributes that XSS filtering always removes.
 *
 * @see hook_xss_disallowed_attributes()
 * @see \Drupal\Component\Utility\Xss::setDisallowedAttributes()
 */
class XssDisallowedAttributesSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Collects the disallowed attributes from modules.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    if ($event->isMainRequest()) {
      Xss::setDisallowedAttributes($this->moduleHandler->invokeAll('xss_disallowed_attributes'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after routing, so that the early bootstrap work (reading config and
    // hook data) has already happened, and before
    // \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber
    // can return a cached response.
    $events[KernelEvents::REQUEST][] = ['onRequest', 28];
    return $events;
  }

}
