<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\Controller\EntityRevisionViewController;
use Drupal\Core\Entity\Controller\VersionHistoryController;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides entity revision routes.
 */
class RevisionHtmlRouteProvider implements EntityRouteProviderInterface {

  /**
   * The entity type definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|null
   */
  protected ?EntityTypeInterface $entityType = NULL;

  /**
   * Constructs a new RevisionHtmlRouteProvider.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface|null $entity_type
   *   The entity type definition.
   */
  public function __construct(?EntityTypeInterface $entity_type = NULL) {
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(?EntityTypeInterface $entity_type = NULL) {
    if ($entity_type !== NULL) {
      @trigger_error('Passing $entity_type to ' . __METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. The entity type is passed on construction. See https://www.drupal.org/node/3593831', E_USER_DEPRECATED);
      $this->entityType = $entity_type;
    }
    $collection = new RouteCollection();
    $entityTypeId = $this->entityType->id();

    if ($version_history_route = $this->getVersionHistoryRoute($this->entityType)) {
      $collection->add("entity.$entityTypeId.version_history", $version_history_route);
    }

    if ($revision_view_route = $this->getRevisionViewRoute($this->entityType)) {
      $collection->add("entity.$entityTypeId.revision", $revision_view_route);
    }

    if ($revision_revert_route = $this->getRevisionRevertRoute($this->entityType)) {
      $collection->add("entity.$entityTypeId.revision_revert_form", $revision_revert_route);
    }

    if ($revision_delete_route = $this->getRevisionDeleteRoute($this->entityType)) {
      $collection->add("entity.$entityTypeId.revision_delete_form", $revision_delete_route);
    }

    return $collection;
  }

  /**
   * Gets the entity revision history route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision revert route, or NULL if the entity type does not
   *   support viewing version history.
   */
  protected function getVersionHistoryRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('version-history')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    return (new Route($entityType->getLinkTemplate('version-history')))
      ->addDefaults([
        '_controller' => VersionHistoryController::class,
        '_title' => 'Revisions',
      ])
      ->setRequirement('_entity_access', $entityTypeId . '.view all revisions')
      ->setOption('entity_type_id', $entityTypeId)
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
      ]);
  }

  /**
   * Gets the entity revision view route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision view route, or NULL if the entity type does not
   *   support viewing revisions.
   */
  protected function getRevisionViewRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('revision')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    $revisionParameterName = $entityTypeId . '_revision';
    return (new Route($entityType->getLinkTemplate('revision')))
      ->addDefaults([
        '_controller' => EntityRevisionViewController::class,
        '_title_callback' => EntityRevisionViewController::class . '::title',
      ])
      ->setRequirement('_entity_access', $revisionParameterName . '.view revision')
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
        $revisionParameterName => [
          'type' => 'entity_revision:' . $entityTypeId,
        ],
      ]);
  }

  /**
   * Gets the entity revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision revert route, or NULL if the entity type does not
   *   support reverting revisions.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('revision-revert-form')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    $revisionParameterName = $entityTypeId . '_revision';
    return (new Route($entityType->getLinkTemplate('revision-revert-form')))
      ->addDefaults([
        '_entity_form' => $entityTypeId . '.revision-revert',
        '_title' => 'Revert revision',
      ])
      ->setRequirement('_entity_access', $revisionParameterName . '.revert')
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
        $revisionParameterName => [
          'type' => 'entity_revision:' . $entityTypeId,
        ],
      ]);
  }

  /**
   * Gets the entity revision delete route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision delete route, or NULL if the entity type does not
   *   support deleting revisions.
   */
  protected function getRevisionDeleteRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('revision-delete-form')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    $revisionParameterName = $entityTypeId . '_revision';
    return (new Route($entityType->getLinkTemplate('revision-delete-form')))
      ->addDefaults([
        '_entity_form' => $entityTypeId . '.revision-delete',
        '_title' => 'Delete revision',
      ])
      ->setRequirement('_entity_access', $revisionParameterName . '.delete revision')
      ->setOption('_admin_route', TRUE)
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
        $revisionParameterName => [
          'type' => 'entity_revision:' . $entityTypeId,
        ],
      ]);
  }

}
