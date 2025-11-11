<?php

declare(strict_types=1);

namespace Drupal\content_moderation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workspaces\Provider\WorkspaceProviderBase;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces\WorkspaceTrackerInterface;

/**
 * Defines the Content Moderation workspace provider.
 */
class ContentModerationWorkspaceProvider extends WorkspaceProviderBase {

  /**
   * The prefix for moderation workspace IDs.
   */
  public const string PREFIX = 'cm';

  /**
   * The prefix for temporary moderation workspace IDs.
   */
  public const string TEMP_PREFIX = 'cm_temp';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceTrackerInterface $workspaceTracker,
    protected WorkspaceInformationInterface $workspaceInfo,
    protected ModerationInformationInterface $moderationInfo,
  ) {
    parent::__construct($entityTypeManager, $workspaceManager, $workspaceTracker, $workspaceInfo);
  }

  /**
   * {@inheritdoc}
   */
  public static function getId(): string {
    return 'content_moderation';
  }

  /**
   * {@inheritdoc}
   */
  public function checkAccess(WorkspaceInterface $workspace, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation === 'view') {
      // Newly created temporary workspaces should always be allowed to be set
      // as active.
      if (str_starts_with($workspace->id(), static::TEMP_PREFIX)) {
        return AccessResult::allowed();
      }

      // Defer access to view moderation workspaces to the moderated entity.
      $moderated_entity_info = $this->getModeratedEntityInfo($workspace);
      $entity = $this->workspaceManager->executeOutsideWorkspace(function () use ($moderated_entity_info) {
        return $this->entityTypeManager->getStorage($moderated_entity_info['entity_type_id'])
          ->load($moderated_entity_info['id'] ?? NULL);
      });
      if ($entity) {
        $access_handler = $this->entityTypeManager->getAccessControlHandler($moderated_entity_info['entity_type_id']);
        return AccessResult::allowedIfHasPermissions($account, ['view latest version', 'view any unpublished content'])
          ->orIf($access_handler->access($entity, 'update', $account, TRUE))
          ->addCacheableDependency($entity);
      }
    }

    return parent::checkAccess($workspace, $operation, $account);
  }

  /**
   * {@inheritdoc}
   */
  public function entityPresave(EntityInterface $entity): void {
    $previous_is_default_revision = $entity->isDefaultRevision();
    parent::entityPresave($entity);

    // Let the moderation handler determine the default revision status for the
    // moderated entity.
    // @see \Drupal\content_moderation\Entity\Handler\ModerationHandler::onPresave()
    $moderated_entity_info = $this->getModeratedEntityInfo($this->workspaceManager->getActiveWorkspace());
    if ($entity->getEntityTypeId() == $moderated_entity_info['entity_type_id'] && $entity->id() == $moderated_entity_info['id']) {
      $entity->isDefaultRevision($previous_is_default_revision);
    }

    // Skip the second workspace revision for entities created in a published
    // moderation state.
    // @see \Drupal\workspaces\Provider\WorkspaceProviderBase::entityPresave()
    if ($entity->isNew()
      && $this->moderationInfo->isModeratedEntity($entity)
      && $this->getModerationState($entity)?->isPublishedState()
      && isset($entity->_temporaryModerationWorkspace)
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

    $storage = $this->entityTypeManager->getStorage('workspace');
    if ($temporary_workspace = $storage->load($entity->_temporaryModerationWorkspace ?? '')) {
      // Move everything to a non-temporary workspace that uses the entity ID
      // instead of its UUID.
      $workspace = $this->createModerationWorkspace($entity);
      $this->workspaceTracker->moveTrackedEntities($temporary_workspace->id(), $workspace->id());

      // Delete the temporary workspace.
      $temporary_workspace->delete();

      // Publish the workspace if needed.
      if ($this->getModerationState($entity)?->isPublishedState()) {
        $workspace->publish();

        // Delete the managed workspace so a new one can be created with the
        // same ID when the entity goes through moderation again.
        // @todo Stop deleting moderation workspaces when they can be archived.
        // @see https://www.drupal.org/project/drupal/issues/2968861
        $workspace->delete();
      }
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
      && ($workspace = $this->getModerationWorkspace($entity->getEntityTypeId(), $entity->id(), $entity->language()->getId()))
    ) {
      $workspace->publish();

      // Delete the managed workspace so a new one can be created with the same
      // ID when the entity goes through moderation again.
      // @todo Stop deleting moderation workspaces when they can be archived.
      // @see https://www.drupal.org/project/drupal/issues/2968861
      $workspace->delete();
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
  public function createModerationWorkspace(EntityInterface $entity, bool $temporary = FALSE): WorkspaceInterface {
    $parts = $temporary
      ? [
        static::TEMP_PREFIX,
        $entity->getEntityTypeId(),
        $entity->bundle(),
        $entity->uuid(),
      ]
      : [
        static::PREFIX,
        $entity->getEntityTypeId(),
        $entity->id(),
        $entity->language()->getId(),
      ];

    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')->create([
      'id' => implode('.', $parts),
      'label' => "CM ({$parts[1]}:{$parts[2]}:{$parts[3]})",
      'provider' => 'content_moderation',
    ]);
    $workspace->save();

    return $workspace;
  }

  /**
   * @todo
   */
  public function getOrCreateModerationWorkspace(string $entity_type_id, int|string $entity_id, string $langcode): WorkspaceInterface {
    if ($workspace = $this->getModerationWorkspace($entity_type_id, $entity_id, $langcode)) {
      return $workspace;
    }
    else {
      $parts = [
        static::PREFIX,
        $entity_type_id,
        $entity_id,
        $langcode,
      ];

      /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
      $workspace = $this->entityTypeManager->getStorage('workspace')->create([
        'id' => implode('.', $parts),
        'label' => "CM ({$parts[1]}:{$parts[2]}:{$parts[3]})",
        'provider' => 'content_moderation',
      ]);
      $workspace->save();

      return $workspace;
    }
  }

  /**
   * @todo
   */
  public function getModerationWorkspace(string $entity_type_id, int|string $entity_id, string $langcode): ?WorkspaceInterface {
    $workspace_id = implode('.', [static::PREFIX, $entity_type_id, $entity_id, $langcode]);

    /** @var \Drupal\workspaces\WorkspaceInterface $workspace */
    $workspace = $this->entityTypeManager->getStorage('workspace')->load($workspace_id);
    return $workspace;
  }

  /**
   * @todo
   */
  protected function getModeratedEntityInfo(WorkspaceInterface $workspace): array {
    $parts = explode('.', $workspace->id());
    $is_temporary = $parts[0] === static::TEMP_PREFIX;
    return [
      'entity_type_id' => $parts[1],
      'bundle' => $is_temporary ? $parts[2] : NULL,
      'uuid' => $is_temporary ? $parts[3] : NULL,
      'id' => !$is_temporary ? $parts[2] : NULL,
      'langcode' => !$is_temporary ? $parts[3] : NULL,
    ];
  }

}
