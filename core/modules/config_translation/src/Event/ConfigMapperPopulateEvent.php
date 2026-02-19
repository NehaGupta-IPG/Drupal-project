<?php

namespace Drupal\config_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a class for events related to configuration translation mappers.
 */
class ConfigMapperPopulateEvent extends Event {

  /**
   * The configuration mapper this event is related to.
   *
   * @var ConfigMapperInterface
   */
  protected $mapper;

  /**
   * The route match this event is related to.
   *
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a ConfigMapperPopulateEvent object.
   *
   * @param ConfigMapperInterface $mapper
   *   The configuration mapper this event is related to.
   * @param RouteMatchInterface $route_match
   *   The route match this event is related to.
   */
  public function __construct(ConfigMapperInterface $mapper, RouteMatchInterface $route_match) {
    $this->mapper = $mapper;
    $this->routeMatch = $route_match;
  }

  /**
   * Gets the configuration mapper this event is related to.
   *
   * @return ConfigMapperInterface
   *   The configuration mapper this event is related to.
   */
  public function getMapper() {
    return $this->mapper;
  }

  /**
   * Gets the route match this event is related to.
   *
   * @return RouteMatchInterface
   *   The route match this event is related to.
   */
  public function getRouteMatch() {
    return $this->routeMatch;
  }

}
