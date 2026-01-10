<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\ParamConverter\ParamConverterManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\Core\Routing\RouteBuildEvent;

/**
 * Event subscriber for registering parameter converters with routes.
 */
class ParamConverterSubscriber implements EventSubscriberInterface {

  /**
   * The parameter converter manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface
   */
  protected $paramConverterManager;

  public function __construct(ParamConverterManagerInterface $param_converter_manager) {
    $this->paramConverterManager = $param_converter_manager;
  }

  /**
   * Applies parameter converters to route parameters.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event to process.
   */
  public function onRoutingRouteAlterSetParameterConverters(RouteBuildEvent $event) {
    $this->paramConverterManager->setRouteParameterConverters($event->getRouteCollection());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run after \Drupal\system\EventSubscriber\AdminRouteSubscriber.
    $events[RoutingEvents::ALTER][] = ['onRoutingRouteAlterSetParameterConverters', -220];
    return $events;
  }

}
