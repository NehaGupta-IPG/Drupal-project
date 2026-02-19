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
   * @param EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\RouteCollection|Route[]
   *   Returns a route collection or an array of routes keyed by name, like
   *   route_callbacks inside 'routing.yml' files.
   */
  public function getRoutes(EntityTypeInterface $entity_type);

}
