<?php

namespace Drupal\taxonomy\Entity\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the taxonomy vocabulary.
 */
class VocabularyRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(?EntityTypeInterface $entity_type = NULL) {
    if ($entity_type !== NULL) {
      @trigger_error('Passing $entity_type to ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. The entity type is passed on construction. See https://www.drupal.org/node/3593831', E_USER_DEPRECATED);
    }
    else {
      $entity_type = $this->entityType;
    }
    $collection = parent::getRoutes();

    if ($reset_page_route = $this->getResetPageRoute($entity_type)) {
      $collection->add("entity.taxonomy_vocabulary.reset_form", $reset_page_route);
    }

    if ($overview_page_route = $this->getOverviewPageRoute($entity_type)) {
      $collection->add("entity.taxonomy_vocabulary.overview_form", $overview_page_route);
    }

    return $collection;
  }

  /**
   * Gets the reset page route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getResetPageRoute(EntityTypeInterface $entity_type) {
    $route = new Route('/admin/structure/taxonomy/manage/{taxonomy_vocabulary}/reset');
    $route->setDefault('_entity_form', 'taxonomy_vocabulary.reset');
    $route->setDefault('_title', 'Reset');
    $route->setRequirement('_entity_access', 'taxonomy_vocabulary.reset all weights');
    $route->setOption('_admin_route', TRUE);
    $route->setOption('parameters', [
      'taxonomy_vocabulary' => [
        'with_config_overrides' => TRUE,
      ],
    ]);

    return $route;
  }

  /**
   * Gets the overview page route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getOverviewPageRoute(EntityTypeInterface $entity_type) {
    $route = new Route('/admin/structure/taxonomy/manage/{taxonomy_vocabulary}/overview');
    $route->setDefault('_title_callback', '\Drupal\Core\Entity\Controller\EntityController::title');
    $route->setDefault('_entity_form', 'taxonomy_vocabulary.overview');
    $route->setRequirement('_entity_access', 'taxonomy_vocabulary.access taxonomy overview');
    $route->setOption('_admin_route', TRUE);
    $route->setOption('parameters', [
      'taxonomy_vocabulary' => [
        'with_config_overrides' => TRUE,
      ],
    ]);

    return $route;
  }

}
