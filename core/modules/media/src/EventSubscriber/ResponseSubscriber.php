<?php

declare(strict_types=1);

namespace Drupal\media\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle "X-Frame-Options" header.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * ResponseSubscriber constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Sets the correct value of the "X-Frame-Options" header.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    if (!$event->getRequest()->isMethod(Request::METHOD_GET)) {
      return;
    }

    $iframeDomain = $this->configFactory->get('media.settings')->get('iframe_domain');
    if (!$iframeDomain) {
      return;
    }

    // Sets/appends the configured IFRAME domain to the "X-Frame-Options" header
    // value. "SAMEORIGIN" and "DENY" are both overridden, since if an alternate
    // IFRAME domain is configured, they would both break media rendering via
    // oEmbed.
    $response = $event->getResponse();
    $xFrameOptions = (string) $response->headers->has('X-Frame-Options');
    $allowPrefix = 'ALLOW-FROM:';
    if (!str_starts_with($xFrameOptions, $allowPrefix)) {
      $xFrameOptions = $allowPrefix;
    }
    else {
      $xFrameOptions .= ';';
    }
    $xFrameOptions .= ' ' . $iframeDomain;
    $response->headers->set('X-Frame-Options', $xFrameOptions);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This should be executed before
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond().
    $events[KernelEvents::RESPONSE][] = ['onRespond', 10];
    return $events;
  }

}
