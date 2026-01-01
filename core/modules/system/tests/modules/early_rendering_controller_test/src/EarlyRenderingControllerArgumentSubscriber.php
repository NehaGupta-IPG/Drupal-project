<?php

declare(strict_types=1);

namespace Drupal\early_rendering_controller_test;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class EarlyRenderingControllerArgumentSubscriber implements EventSubscriberInterface {

  public function onKernelController(ControllerArgumentsEvent $event): void {
    $arguments = $event->getArguments();
    if (count($arguments) === 0) {
      return;
    }

    if ($arguments[0] === 'foo') {
      $event->setArguments(['bar']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::CONTROLLER_ARGUMENTS => ['onKernelController', 1],
    ];
  }

}
