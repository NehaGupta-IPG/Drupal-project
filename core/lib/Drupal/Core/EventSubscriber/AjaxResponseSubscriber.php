<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an AjaxResponseSubscriber object.
   *
   * @param \Closure $processorClosure
   *   The AJAX response attachments processor service, wrapped in a closure.
   */
  public function __construct(
    #[AutowireServiceClosure('ajax_response.attachments_processor')]
    protected \Closure $processorClosure,
  ) {}

  /**
   * Request parameter to indicate that a request is a Drupal Ajax request.
   */
  const AJAX_REQUEST_PARAMETER = '_drupal_ajax';

  /**
   * Sets the AJAX parameter from the current request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The response event, which contains the current request.
   */
  public function onRequest(RequestEvent $event) {
    // Pass to the Html class that the current request is an Ajax request.
    $request = $event->getRequest();
    if ($request->query->get(static::AJAX_REQUEST_PARAMETER) || $request->request->get(static::AJAX_REQUEST_PARAMETER)) {
      Html::setIsAjax(TRUE);
    }
  }

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    if ($response instanceof AjaxResponse) {
      ($this->processorClosure)()->processAttachments($response);

      // User-uploaded files cannot set any response headers, so a custom header
      // is used to indicate to ajax.js that this response is safe. Note that
      // most Ajax requests bound using the Form API will be protected by having
      // the URL flagged as trusted in Drupal.settings, so this header is used
      // only for things like custom markup that gets Ajax behaviors attached.
      $response->headers->set('X-Drupal-Ajax-Token', 1);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse', -100];
    $events[KernelEvents::REQUEST][] = ['onRequest', 50];

    return $events;
  }

}
