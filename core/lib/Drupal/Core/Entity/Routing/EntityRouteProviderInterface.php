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
   * @param \Drupal\Core\Entity\EntityTypeInterface|null $entity_type
   *   (optional) The entity type definition. Deprecated as of drupal:11.4.0;
   *   the entity type is passed on construction via
   *   \Drupal\Core\Entity\EntityHandlerInterface::createInstance() and must not
   *   be passed here.
   *
   * @return \Symfony\Component\Routing\RouteCollection|\Symfony\Component\Routing\Route[]
   *   Returns a route collection or an array of routes keyed by name, like
   *   route_callbacks inside 'routing.yml' files.
   */
  public function getRoutes(?EntityTypeInterface $entity_type = NULL);

}
