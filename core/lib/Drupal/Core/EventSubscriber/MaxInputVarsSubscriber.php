<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Max Input Var subscriber to catch error early.
 */
class MaxInputVarsSubscriber implements EventSubscriberInterface {

  public function __construct(private readonly MessengerInterface $messenger) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // This should happen first.
    $events[KernelEvents::REQUEST][] = ['onRequest', 2000];
    return $events;
  }

  /**
   * Check for the max input vars early in the request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   *
   * @return void
   */
  public function onRequest(RequestEvent $event) {
    $error = error_get_last();
    if (!empty($error['message']) && strstr($error['message'], 'Input variables exceeded') !== FALSE) {
      // Prevent forms from processing.
      FormBuilder::setMaxInputVars();

      // Add the error message as a Drupal Error.
      $this->messenger->addError($error['message']);

      // Clear the existing output.
      ob_clean();
    }
  }

}
