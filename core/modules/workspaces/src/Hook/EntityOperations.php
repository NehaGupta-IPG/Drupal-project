<?php

declare(strict_types=1);

namespace Drupal\workspaces\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\Order\Order;
use Drupal\Core\Hook\Order\OrderBefore;
use Drupal\content_moderation\Hook\ContentModerationHooks;
use Drupal\workspaces\WorkspaceAssociationInterface;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Drupal\workspaces\WorkspaceRepositoryInterface;

/**
 * Defines a class for reacting to entity runtime hooks.
 */
class EntityOperations {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceAssociationInterface $workspaceAssociation,
    protected WorkspaceInformationInterface $workspaceInfo,
    protected WorkspaceRepositoryInterface $workspaceRepository,
  ) {}

  /**
   * Implements hook_entity_preload().
   */
  #[Hook('entity_preload')]
  public function entityPreload(array $ids, string $entity_type_id): array {
    $entities = [];

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!$this->workspaceInfo->isEntityTypeSupported($entity_type) || !$this->workspaceManager->hasActiveWorkspace()) {
      return $entities;
    }

    // Get a list of revision IDs for entities that have a revision set for the
    // current active workspace. If an entity has multiple revisions set for a
    // workspace, only the one with the highest ID is returned.
    if ($tracked_entities = $this->workspaceAssociation->getTrackedEntities($this->workspaceManager->getActiveWorkspace()->id(), $entity_type_id, $ids)) {
      // Bail out early if there are no tracked entities of this type.
      if (!isset($tracked_entities[$entity_type_id])) {
        return $entities;
      }

      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity_type_id);

      // Swap out every entity which has a revision set for the current active
      // workspace.
      foreach ($storage->loadMultipleRevisions(array_keys($tracked_entities[$entity_type_id])) as $revision) {
        $entities[$revision->id()] = $revision;
      }
    }

    return $entities;
  }

  /**
   * Implements hook_entity_presave().
   */
  #[Hook('entity_presave', order: Order::First)]
  #[ReorderHook('entity_presave',
    class: ContentModerationHooks::class,
    method: 'entityPresave',
    order: new OrderBefore(['workspaces'])
  )]
  public function entityPresave(EntityInterface $entity): void {
    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->workspaceManager->getProvider($active_workspace)->entityPresave($entity);
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert', order: Order::Last)]
  public function entityInsert(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'workspace') {
      $this->workspaceAssociation->workspaceInsert($entity);
      $this->workspaceRepository->resetCache();
    }

    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->workspaceManager->getProvider($active_workspace)->entityInsert($entity);
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'workspace') {
      $this->workspaceRepository->resetCache();
    }

    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->workspaceManager->getProvider($active_workspace)->entityUpdate($entity);
    }
  }

  /**
   * Implements hook_entity_translation_insert().
   */
  #[Hook('entity_translation_insert')]
  public function entityTranslationInsert(EntityInterface $translation): void {
    if ($this->shouldSkipOperations($translation)
      || !$this->workspaceInfo->isEntitySupported($translation)
      || $translation->isSyncing()
    ) {
      return;
    }

    // When a new translation is added to an existing entity, we need to add
    // that translation to the default revision as well, otherwise the new
    // translation wouldn't show up in entity queries or views which use the
    // field data table as the base table.
    $default_revision = $this->workspaceManager->executeOutsideWorkspace(function () use ($translation) {
      return $this->entityTypeManager
        ->getStorage($translation->getEntityTypeId())
        ->load($translation->id());
    });
    $langcode = $translation->language()->getId();
    if (!$default_revision->hasTranslation($langcode)) {
      $default_revision_translation = $default_revision->addTranslation($langcode, $translation->toArray());
      assert($default_revision_translation instanceof EntityPublishedInterface);
      $default_revision_translation->setUnpublished();
      $default_revision_translation->setSyncing(TRUE);
      $default_revision_translation->save();
    }
  }

  /**
   * Implements hook_entity_predelete().
   */
  #[Hook('entity_predelete')]
  public function entityPredelete(EntityInterface $entity): void {
    if ($entity->getEntityTypeId() === 'workspace') {
      $this->workspaceRepository->resetCache();
    }

    if ($active_workspace = $this->workspaceManager->getActiveWorkspace()) {
      $this->workspaceManager->getProvider($active_workspace)->entityPredelete($entity);
    }
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($this->workspaceInfo->isEntityTypeSupported($entity->getEntityType())) {
      $this->workspaceAssociation->deleteAssociations(NULL, $entity->getEntityTypeId(), [$entity->id()]);
    }
  }

  /**
   * Implements hook_entity_revision_delete().
   */
  #[Hook('entity_revision_delete')]
  public function entityRevisionDelete(EntityInterface $entity): void {
    if ($this->workspaceInfo->isEntityTypeSupported($entity->getEntityType())) {
      $this->workspaceAssociation->deleteAssociations(NULL, $entity->getEntityTypeId(), [$entity->id()], [$entity->getRevisionId()]);
    }
  }

  /**
   * Implements hook_entity_query_tag__TAG_alter() for 'latest_translated_affected_revision'.
   */
  #[Hook('entity_query_tag__latest_translated_affected_revision_alter')]
  public function entityQueryTagLatestTranslatedAffectedRevisionAlter(QueryInterface $query): void {
    $entity_type = $this->entityTypeManager->getDefinition($query->getEntityTypeId());
    if (!$this->workspaceInfo->isEntityTypeSupported($entity_type) || !$this->workspaceManager->hasActiveWorkspace()) {
      return;
    }

    $active_workspace = $this->workspaceManager->getActiveWorkspace();
    $tracked_entities = $this->workspaceAssociation->getTrackedEntities($active_workspace->id());

    if (!isset($tracked_entities[$entity_type->id()])) {
      return;
    }

    if ($revision_id = array_search($query->getMetaData('entity_id'), $tracked_entities[$entity_type->id()])) {
      $query->condition($entity_type->getKey('revision'), $revision_id, '<=');
      $conditions = $query->orConditionGroup();
      $conditions->condition($entity_type->getRevisionMetadataKey('workspace'), $active_workspace->id());
      $conditions->condition($entity_type->getRevisionMetadataKey('revision_default'), TRUE);
      $query->condition($conditions);
    }
  }

  /**
   * Implements hook_form_alter().
   *
   * Alters entity forms to disallow concurrent editing in multiple workspaces.
   */
  #[Hook('form_alter', order: Order::First)]
  public function entityFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!$form_state->getFormObject() instanceof EntityFormInterface) {
      return;
    }

    $entity = $form_state->getFormObject()->getEntity();
    if (!$this->workspaceInfo->isEntitySupported($entity) && !$this->workspaceInfo->isEntityIgnored($entity)) {
      return;
    }

    // For supported and ignored entity types, signal the fact that this form is
    // safe to use in a workspace.
    // @see \Drupal\workspaces\Hook\FormOperations::formAlter()
    $form_state->set('workspace_safe', TRUE);

    // There is nothing more to do for ignored entity types.
    if ($this->workspaceInfo->isEntityIgnored($entity)) {
      return;
    }

    // Add an entity builder to the form which marks the edited entity object as
    // a pending revision. This is needed so validation constraints like
    // \Drupal\path\Plugin\Validation\Constraint\PathAliasConstraintValidator
    // know in advance (before hook_entity_presave()) that the new revision will
    // be a pending one.
    if ($this->workspaceManager->getActiveWorkspace()) {
      $form['#entity_builders'][] = [static::class, 'entityFormEntityBuild'];
    }
  }

  /**
   * Entity builder that marks all supported entities as pending revisions.
   */
  public static function entityFormEntityBuild(string $entity_type_id, RevisionableInterface $entity, array &$form, FormStateInterface &$form_state): void {
    // Ensure that all entity forms are signaling that a new revision will be
    // created.
    $entity->setNewRevision(TRUE);

    // Set the non-default revision flag so that validation constraints are also
    // aware that a pending revision is about to be created.
    $entity->isDefaultRevision(FALSE);
  }

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
    // We should not react on entity operations when the entity is ignored or
    // when we're not in a workspace context.
    return $this->workspaceInfo->isEntityIgnored($entity) || !$this->workspaceManager->hasActiveWorkspace();
  }

}
