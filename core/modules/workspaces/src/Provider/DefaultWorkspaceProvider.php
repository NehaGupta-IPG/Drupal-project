<?php

declare(strict_types=1);

namespace Drupal\workspaces\Provider;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the default workspace provider.
 */
class DefaultWorkspaceProvider extends WorkspaceProviderBase {

  /**
   * {@inheritdoc}
   */
  public static function getId(): string {
    return 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity): void {
    // Disallow any change to an unsupported entity when we are not in the
    // default workspace.
    if (!$this->workspaceInfo->isEntitySupported($entity)) {
      throw new \RuntimeException(sprintf('The "%s" entity type can only be saved in the default workspace.', $entity->getEntityTypeId()));
    }

    parent::entityPresave($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function entityPredelete(EntityInterface $entity): void {
    // Prevent the entity from being deleted if the entity type does not have
    // support for workspaces, or if the entity has a published default
    // revision.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if (!$this->workspaceInfo->isEntitySupported($entity) || !$this->workspaceInfo->isEntityDeletable($entity, $active_workspace)) {
      throw new \RuntimeException("This {$entity->getEntityType()->getSingularLabel()} can only be deleted in the Live workspace.");
    }

    parent::entityPredelete($entity);
  }

}
