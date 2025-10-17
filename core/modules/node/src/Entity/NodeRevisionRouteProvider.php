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
