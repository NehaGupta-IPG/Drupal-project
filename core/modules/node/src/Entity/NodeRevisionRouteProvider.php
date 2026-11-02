<?php

namespace Drupal\node\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for nodes.
 */
class NodeRevisionRouteProvider extends RevisionHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $routes = parent::getRoutes($entity_type);
    // Rename the entity.node.revision_revert_form and
    // entity.node.revision_delete_form routes to keep BC.
    // @todo remove this and use an alias instead when https://www.drupal.org/project/drupal/issues/3506653 is done.
    $revisionRevertRoute = $routes->get('entity.node.revision_revert_form');
    $routes->remove('entity.node.revision_revert_form');
    $routes->add('node.revision_revert_confirm', $revisionRevertRoute);

    $addFormRoute = $routes->get('entity.node.revision_delete_form');
    $routes->remove('entity.node.revision_delete_form');
    $routes->add('node.revision_delete_confirm', $addFormRoute);
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  protected function getVersionHistoryRoute(EntityTypeInterface $entityType): ?Route {
    $route = parent::getVersionHistoryRoute($entityType);
    $route->setOption('_node_operation_route', TRUE);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entityType): ?Route {
    $route = parent::getRevisionRevertRoute($entityType);
    $route->setOption('_node_operation_route', TRUE);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  protected function getRevisionDeleteRoute(EntityTypeInterface $entityType): ?Route {
    $route = parent::getRevisionDeleteRoute($entityType);
    $route->setOption('_node_operation_route', TRUE);
    return $route;
  }

}
