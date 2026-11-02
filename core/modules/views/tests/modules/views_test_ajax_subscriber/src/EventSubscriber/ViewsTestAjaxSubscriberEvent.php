<?php

declare(strict_types=1);

namespace Drupal\views_test_ajax_subscriber\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber used by EmbeddedViewPaginationAJAXTest.
 */
class ViewsTestAjaxSubscriberEvent implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

  /**
   * This method is called whenever the kernel.response event is dispatched.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   *
   * @throws \Drupal\views_test_ajax_subscriber\EventSubscriber\UnexpectedViewAjaxException
   */
  public function onResponse(ResponseEvent $event): void {
    /** @var \Drupal\views\Ajax\ViewAjaxResponse $response */
    $response = $event->getResponse();

    // Only alter views ajax responses.
    if (!($response instanceof ViewAjaxResponse)) {
      return;
    }

    $expectedView = \Drupal::state()->get('views_test_ajax_subscriber');
    if ($expectedView && $response->getView()->id() != $expectedView) {
      throw new UnexpectedViewAjaxException("Expected: {$expectedView}. Got: {$response->getView()->id()}.");
    }
  }

}

/**
 * Exception thrown when the AJAX request comes from the wrong view.
 */
class UnexpectedViewAjaxException extends \Exception {}
