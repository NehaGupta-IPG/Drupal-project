<?php

declare(strict_types=1);

namespace Drupal\block_content\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Provides HTML routes for block_content_type entities.
 */
class BlockContentTypeRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(?EntityTypeInterface $entity_type = NULL) {
    if ($entity_type !== NULL) {
      @trigger_error('Passing $entity_type to ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. The entity type is passed on construction. See https://www.drupal.org/node/3593831', E_USER_DEPRECATED);
    }
    $routes = parent::getRoutes();
    // Rename the entity.block_content_type.add_form route to keep BC.
    // @todo remove this and use an alias instead when https://www.drupal.org/project/drupal/issues/3506653 is done.
    $addFormRoute = $routes->get('entity.block_content_type.add_form');
    $routes->remove('entity.block_content_type.add_form');
    $routes->add('block_content.type_add', $addFormRoute);
    return $routes;
  }

}
