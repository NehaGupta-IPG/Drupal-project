<?php

declare(strict_types=1);

namespace Drupal\workspaces\Routing;

use Drupal\content_moderation\Entity\Routing\EntityModerationRouteProvider as ContentModerationEntityModerationRouteProvider;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Overrides the content moderation provided entity moderation provider.
 */
final class EntityModerationRouteProvider extends ContentModerationEntityModerationRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getLatestVersionRoute(EntityTypeInterface $entity_type): Route {
    $route = parent::getLatestVersionRoute($entity_type);
    if ($route !== NULL) {
      // Non-default workspaces display the active revision on the canonical
      // route of an entity, so the latest version route is no longer needed.
      // Add an access callback to the route to allow for an active workspace.
      $route->setRequirement('_has_active_workspace', 'TRUE');
    }
    return $route;
  }

}
