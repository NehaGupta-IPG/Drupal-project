<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Allows entity types to provide routes.
 */
interface EntityRouteProviderInterface {

  /**
   * Provides routes for entities.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition. Deprecated as of drupal:11.5.0; the entity
   *   type is now passed on construction via
   *   \Drupal\Core\Entity\EntityHandlerInterface::createInstance() and the
   *   $entity_type parameter is no longer passed by core. Implementations
   *   should make the parameter optional, i.e.
   *   getRoutes(?EntityTypeInterface $entity_type = NULL), and read the entity
   *   type from the value injected on construction. The parameter is kept
   *   required in this interface for backwards compatibility and will be made
   *   optional in drupal:13.0.0.
   *
   * @return \Symfony\Component\Routing\RouteCollection|\Symfony\Component\Routing\Route[]
   *   Returns a route collection or an array of routes keyed by name, like
   *   route_callbacks inside 'routing.yml' files.
   */
  public function getRoutes(EntityTypeInterface $entity_type);

}
