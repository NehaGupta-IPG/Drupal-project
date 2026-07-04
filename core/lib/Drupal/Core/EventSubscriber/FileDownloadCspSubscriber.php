<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\FileSecurity\FileSecurity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber to add Content-Security-Policy header to file downloads.
 *
 * Adds a CSP-header when downloading SVG-files, to prevent embedded scripts
 * from running in the browser.
 */
class FileDownloadCspSubscriber implements EventSubscriberInterface {

  /**
   * Sets Content-Security-Policy on file downloads.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $response = $event->getResponse();

    if (!($response instanceof BinaryFileResponse)) {
      return;
    }

    if (
      ($response->headers->get('Content-Type') === 'image/svg+xml'
        || in_array(
          pathinfo($event->getRequest()->getPathInfo(), PATHINFO_EXTENSION),
          ['svg', 'svgz'],
          TRUE
        )
      )
      && !$response->headers->has('Content-Security-Policy')
    ) {
      $response->headers->set('Content-Security-Policy', FileSecurity::CSP_SVG_HEADER, FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
