<?php

namespace Drupal\file\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides routes for files.
 */
class FileRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(?EntityTypeInterface $entity_type = NULL) {
    if ($entity_type !== NULL) {
      @trigger_error('Passing $entity_type to ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. The entity type is passed on construction. See https://www.drupal.org/node/3593831', E_USER_DEPRECATED);
    }
    $route_collection = new RouteCollection();

    $route = (new Route('/file/{file}/delete'))
      ->addDefaults([
        '_entity_form' => 'file.delete',
        '_title' => 'Delete',
      ])
      ->setRequirement('file', '\d+')
      ->setRequirement('_entity_access', 'file.delete')
      ->setOption('_admin_route', TRUE);
    $route_collection->add('entity.file.delete_form', $route);

    return $route_collection;
  }

}
