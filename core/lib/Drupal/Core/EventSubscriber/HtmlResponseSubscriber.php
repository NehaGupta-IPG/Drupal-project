<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle HTML responses.
 */
class HtmlResponseSubscriber implements EventSubscriberInterface {

  /**
   * The HTML response attachments processor service.
   *
   * @var AttachmentsResponseProcessorInterface
   */
  protected $htmlResponseAttachmentsProcessor;

  /**
   * Constructs a HtmlResponseSubscriber object.
   *
   * @param AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   */
  public function __construct(AttachmentsResponseProcessorInterface $html_response_attachments_processor) {
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
  }

  /**
   * Processes attachments for HtmlResponse responses.
   *
   * @param ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof HtmlResponse) {
      return;
    }

    $event->setResponse($this->htmlResponseAttachmentsProcessor->processAttachments($response));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
