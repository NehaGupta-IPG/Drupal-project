<?php

declare(strict_types=1);

namespace Drupal\content_moderation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\workspaces\Entity\Workspace;
use Drupal\workspaces\Provider\WorkspaceProviderBase;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;

/**
 * Defines the Content Moderation workspace provider.
 */
class ContentModerationWorkspaceProvider extends WorkspaceProviderBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceAssociationInterface $workspaceAssociation,
    protected WorkspaceInformationInterface $workspaceInfo,
    protected ModerationInformationInterface $moderationInfo,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity): void {
    parent::entityPresave($entity);

    // Skip the second workspace revision for entities created in a published
    // moderation state.
    // @see \Drupal\workspaces\Provider\WorkspaceProviderBase::entityPresave()
    if ($entity->isNew()
      && $this->moderationInfo->isModeratedEntity($entity)
      && $this->getModerationState($entity)?->isPublishedState()
      && $this->getModerationWorkspace($entity->getEntityTypeId(), NULL, $entity->uuid())
      && isset($this->initialPublished[$entity->uuid()])
    ) {
      unset($this->initialPublished[$entity->uuid()]);
      $entity->setPublished();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityInsert(EntityInterface $entity): void {
    parent::entityInsert($entity);

    if ($temporary_workspace = $this->getModerationWorkspace($entity->getEntityTypeId(), NULL, $entity->uuid())) {
      // Move everything to a non-temporary workspace that uses the entity ID
      // instead of its UUID.
      $id = implode('.', ['cm', $entity->getEntityTypeId(), $entity->id()]);
      $workspace = Workspace::create([
        'id' => $id,
        'label' => "Managed ($id)",
        'provider' => 'content_moderation',
        'managed' => TRUE,
      ]);
      $workspace->save();

      // @todo Add WorkspaceAssociationInterface::moveToAnotherWorkspace().
      // Ensure that entities are moved outside of a workspace context.
      $this->workspaceManager->executeOutsideWorkspace(function () use ($temporary_workspace, $workspace) {
        foreach ($this->workspaceAssociation->getTrackedEntities($temporary_workspace->id()) as $entity_type_id => $entity_ids) {
          $affected_revision_ids = $this->workspaceAssociation->getAssociatedRevisions($temporary_workspace->id(), $entity_type_id, $entity_ids);
          $affected_revisions = $this->entityTypeManager->getStorage($entity_type_id)
            ->loadMultipleRevisions(array_keys($affected_revision_ids));

          foreach ($affected_revisions as $revision) {
            $field_name = $revision->getEntityType()
              ->getRevisionMetadataKey('workspace');
            $revision->{$field_name}->target_id = $workspace->id();
            $revision->setSyncing(TRUE);
            $revision->save();

            // Delete the association entries for the source workspace, and
            // track the entity in the target workspace.
            $this->workspaceAssociation->deleteAssociations($temporary_workspace->id(), $revision->getEntityTypeId(), [$revision->id()]);
            $this->workspaceAssociation->trackEntity($revision, $workspace);
          }
        }
      });

      // Publish the workspace if needed.
      if ($this->getModerationState($entity)?->isPublishedState()) {
        $workspace->publish();
      }

      // Delete the temporary workspace.
      $this->workspaceAssociation->deleteAssociations($temporary_workspace->id());
      $temporary_workspace->delete();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function entityUpdate(EntityInterface $entity): void {
    parent::entityUpdate($entity);

    // Publish the workspace if needed.
    if ($this->moderationInfo->isModeratedEntity($entity)
      && $this->getModerationState($entity)?->isPublishedState()
      && ($workspace = $this->getModerationWorkspace($entity->getEntityTypeId(), $entity->id()))
    ) {
      $workspace->publish();
    }
  }

  /**
   * @todo
   */
  protected function getModerationState(EntityInterface $entity): ?ContentModerationState {
    if ($entity->moderation_state->value) {
      $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
      /** @var \Drupal\content_moderation\ContentModerationState $current_state */
      $current_state = $workflow->getTypePlugin()->getState($entity->moderation_state->value);
    }

    return $current_state ?? NULL;
  }

  /**
   * @todo
   */
  public function getModerationWorkspace(string $entity_type_id, $entity_id = NULL, $entity_uuid = NULL): ?WorkspaceInterface {
    assert($entity_id || $entity_uuid);

    $result = $this->entityTypeManager->getStorage('workspace')
      ->loadByProperties([
        'id' => 'cm.' . $entity_type_id . '.' . ($entity_id ?: $entity_uuid),
        'provider' => 'content_moderation',
      ]);

    return $result ? reset($result) : NULL;
  }

}
