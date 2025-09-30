<?php

declare(strict_types=1);

namespace Drupal\request_test\EventSubscriber;

use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\State\StateInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Counts ajax requests.
 */
class AjaxRequestSubscriber implements EventSubscriberInterface {

  public const STORAGE_KEY = 'request_count';

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * RequestSubscriber constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   Storage that persists between multiple requests.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[KernelEvents::REQUEST][] = ['countAjaxRequest'];
    return $events;
  }

  /**
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Event callback.
   */
  public function countAjaxRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if (!$request->get(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER)) {
      return;
    }

    $this->incrementCount();
  }

  public function getCount(): int {
    return $this->state->get(self::STORAGE_KEY, 0);
  }

  private function incrementCount(): void {
    $requestCount = $this->getCount();
    $requestCount++;
    $this->state->set(self::STORAGE_KEY, $requestCount);
  }

}
