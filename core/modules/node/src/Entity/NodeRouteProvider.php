<?php

namespace Drupal\node\Entity;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\node\Controller\NodeController;

/**
 * Provides routes for nodes.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The node
 *   entity type now uses \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 *   with declarative route settings. See the "route_settings" key on the node
 *   entity type definition.
 *
 * @see https://www.drupal.org/node/9999999
 * @see \Drupal\node\Entity\Node
 * @see \Drupal\Core\Entity\EntityType::$route_settings
 */
class NodeRouteProvider extends DefaultHtmlRouteProvider {

  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. The node entity type now uses \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider with declarative route settings. See https://www.drupal.org/node/9999999', E_USER_DEPRECATED);
    parent::__construct($entity_type_manager, $entity_field_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $routes = parent::getRoutes($entity_type);
    // Rename the entity.node.add_form and entity.node.add_page routes to keep
    // BC.
    // @todo remove this and use an alias instead when https://www.drupal.org/project/drupal/issues/3506653 is done.
    $addPageRoute = $routes->get('entity.node.add_page');
    $routes->remove('entity.node.add_page');
    $routes->add('node.add_page', $addPageRoute);

    $addFormRoute = $routes->get('entity.node.add_form');
    $routes->remove('entity.node.add_form');
    $routes->add('node.add', $addFormRoute);
    return $routes;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddPageRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddPageRoute($entity_type)) {
      return $route
        ->setDefault('_controller', NodeController::class . '::addPage')
        ->setDefault('_title', 'Add content')
        ->setOption('_node_operation_route', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getAddFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getAddFormRoute($entity_type)) {
      return $route
        ->setDefault('_title_callback', NodeController::class . '::addPageTitle')
        ->setOption('_node_operation_route', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getEditFormRoute($entity_type)) {
      return $route->setOption('_node_operation_route', TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeleteFormRoute(EntityTypeInterface $entity_type) {
    if ($route = parent::getDeleteFormRoute($entity_type)) {
      return $route->setOption('_node_operation_route', TRUE);
    }
  }

}
