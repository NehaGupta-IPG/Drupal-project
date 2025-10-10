<?php

declare(strict_types=1);

namespace Drupal\workspaces\Provider;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * @todo
 */
abstract class WorkspaceProviderBase implements WorkspaceProviderInterface {

  /**
   * A list of entity UUIDs that were created as published in a workspace.
   */
  protected array $initialPublished = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceAssociationInterface $workspaceAssociation,
    protected WorkspaceInformationInterface $workspaceInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function entityCreate(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function entityPreload(EntityInterface $entity): void {}

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity): void {
    if ($this->shouldSkipOperations($entity)) {
      return;
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface|\Drupal\Core\Entity\EntityPublishedInterface $entity */
    if (!$entity->isNew() && !$entity->isSyncing()) {
      // Force a new revision if the entity is not replicating.
      $entity->setNewRevision(TRUE);

      // All entities in the non-default workspace are pending revisions,
      // regardless of their publishing status. This means that when creating
      // a published pending revision in a non-default workspace it will also be
      // a published pending revision in the default workspace, however, it will
      // become the default revision only when it is replicated to the default
      // workspace.
      $entity->isDefaultRevision(FALSE);
    }

    // In ::entityFormEntityBuild() we mark the entity as a non-default revision
    // so that validation constraints can rely on $entity->isDefaultRevision()
    // always returning FALSE when an entity form is submitted in a workspace.
    // However, after validation has run, we need to revert that flag so the
    // first revision of a new entity is correctly seen by the system as the
    // default revision.
    if ($entity->isNew()) {
      $entity->isDefaultRevision(TRUE);
    }

    // Track the workspaces in which the new revision was saved.
    if (!$entity->isSyncing()) {
      $field_name = $entity->getEntityType()->getRevisionMetadataKey('workspace');
      $entity->{$field_name}->target_id = $this->workspaceManager->getActiveWorkspace()->id();
    }

    // When a new published entity is inserted in a non-default workspace, we
    // actually want two revisions to be saved:
    // - An unpublished default revision in the default ('live') workspace.
    // - A published pending revision in the current workspace.
    if ($entity->isNew() && $entity->isPublished()) {
      // Keep track of the initially published entities for ::entityInsert(),
      // then unpublish the default revision.
      $this->initialPublished[$entity->uuid()] = TRUE;
      $entity->setUnpublished();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityInsert(EntityInterface $entity): void {
    if ($this->shouldSkipOperations($entity) || !$this->workspaceInfo->isEntitySupported($entity)) {
      return;
    }

    assert($entity instanceof RevisionableInterface && $entity instanceof EntityPublishedInterface);
    $this->workspaceAssociation->trackEntity($entity, $this->workspaceManager->getActiveWorkspace());

    // When a published entity is created in a workspace, it should remain
    // published only in that workspace, and unpublished in the live workspace.
    // It is first saved as unpublished for the default revision, then
    // immediately a second revision is created which is published and attached
    // to the workspace. This ensures that the initial version of the entity
    // does not 'leak' into the live site. This differs from edits to existing
    // entities where there is already a valid default revision for the live
    // workspace.
    if (isset($this->initialPublished[$entity->uuid()])) {
      // Ensure that the default revision of an entity saved in a workspace is
      // unpublished.
      if ($entity->isPublished()) {
        throw new \RuntimeException('The default revision of an entity created in a workspace cannot be published.');
      }

      $entity->setPublished();
      // Ensure that the second (workspace-specific) revision is marked as new
      // early, so operations that are executed before the entity presave hook
      // (e.g. field-level presave) can take that into account.
      $entity->setNewRevision();
      $entity->isDefaultRevision(FALSE);
      $entity->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityUpdate(EntityInterface $entity): void {
    if ($this->shouldSkipOperations($entity) || !$this->workspaceInfo->isEntitySupported($entity)) {
      return;
    }

    // Only track new revisions.
    /** @var \Drupal\Core\Entity\RevisionableInterface $entity */
    if ($entity->getLoadedRevisionId() != $entity->getRevisionId()) {
      $this->workspaceAssociation->trackEntity($entity, $this->workspaceManager->getActiveWorkspace());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityPredelete(EntityInterface $entity): void {
    if ($this->shouldSkipOperations($entity)) {
      return;
    }

    // Prevent the entity from being deleted if the entity type does not have
    // support for workspaces, or if the entity has a published default
    // revision.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    if (!$this->workspaceInfo->isEntitySupported($entity) || !$this->workspaceInfo->isEntityDeletable($entity, $active_workspace)) {
      throw new \RuntimeException("This {$entity->getEntityType()->getSingularLabel()} can only be deleted in the Live workspace.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityDelete(EntityInterface $entity): void {}

  /**
   * Determines whether we need to react on entity operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   Returns TRUE if entity operations should not be altered, FALSE otherwise.
   */
  protected function shouldSkipOperations(EntityInterface $entity): bool {
    // We should not react on entity operations when the entity is ignored.
    return $this->workspaceInfo->isEntityIgnored($entity);
  }

}
