<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Entity\User;
use Drupal\user\Event\EntityCancellationUnpublishEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Applies revision-safe account-cancellation effects to content entities.
 *
 * Authorship and publication changes are saved through the entity API so that
 * entity hooks, cache invalidation, and storage-specific behavior are kept.
 * The synchronization flag prevents these administrative updates from being
 * treated as editorial changes by revision and moderation workflows.
 *
 * Large workloads are handled by paginating the underlying entity query with
 * a cursor rather than materializing every matching identifier in memory.
 *
 * @internal
 */
final class EntityCancellationProcessor {

  /**
   * Number of entity operations processed in one batch pass.
   */
  private const BATCH_CHUNK_SIZE = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * Reassigns ownership to anonymous.
   *
   * @param \Drupal\Core\Entity\ContentEntityTypeInterface $entity_type
   *   The entity type to process.
   * @param int $source_uid
   *   The account identifier whose content must be reassigned.
   * @param array $reassignment_values
   *   (optional) Additional field values to set on every translation.
   */
  public function reassign(
    ContentEntityTypeInterface $entity_type,
    int $source_uid,
    array $reassignment_values = [],
  ): void {
    $plans = [];
    $owner_field = $entity_type->getKey('owner');
    if ($owner_field) {
      $plans[] = $this->plan(
        'reassign',
        $owner_field,
        $source_uid,
        TRUE,
        $reassignment_values,
      );
    }
    $revision_user_field = $entity_type
      ->getRevisionMetadataKey('revision_user');
    if ($revision_user_field && $revision_user_field !== $owner_field) {
      $plans[] = $this->plan(
        'anonymize_revision',
        $revision_user_field,
        $source_uid,
        TRUE,
      );
    }
    $this->runPlans($entity_type, $plans);
  }

  /**
   * Unpublishes owned entities.
   */
  public function unpublish(
    ContentEntityTypeInterface $entity_type,
    int $uid,
  ): void {
    $owner_field = $entity_type->getKey('owner');
    if (!$owner_field || !$entity_type->getKey('published')) {
      return;
    }
    $this->runPlans($entity_type, [
      // Query every revision and filter to the default revision after loading.
      // This prevents workspace query decorators from substituting a pending
      // workspace revision for the canonical revision.
      $this->plan('unpublish', $owner_field, $uid, TRUE),
    ]);
  }

  /**
   * Deletes owned entities and anonymizes matching revision authors.
   */
  public function delete(
    ContentEntityTypeInterface $entity_type,
    int $uid,
  ): void {
    $plans = [];
    $owner_field = $entity_type->getKey('owner');
    if ($owner_field) {
      // Delete owned entities and historical revisions in one pass. Deleting a
      // default revision removes the entity and all of its revisions.
      $plans[] = $this->plan('delete_owned', $owner_field, $uid, TRUE);
    }
    $revision_user_field = $entity_type
      ->getRevisionMetadataKey('revision_user');
    if ($revision_user_field && $revision_user_field !== $owner_field) {
      // Preserve revisions edited by the account when their content belongs to
      // someone else, but remove the reference to the deleted account.
      $plans[] = $this->plan(
        'anonymize_revision',
        $revision_user_field,
        $uid,
        TRUE,
      );
    }
    $this->runPlans($entity_type, $plans);
  }

  /**
   * Builds a plan descriptor.
   */
  private function plan(
    string $operation,
    string $field,
    int $uid,
    bool $all_revisions,
    array $reassignment_values = [],
  ): array {
    return [
      'operation' => $operation,
      'field' => $field,
      'uid' => $uid,
      'all_revisions' => $all_revisions,
      'reassignment_values' => $reassignment_values,
    ];
  }

  /**
   * Adds plans to an active batch, or executes them synchronously otherwise.
   */
  private function runPlans(
    ContentEntityTypeInterface $entity_type,
    array $plans,
  ): void {
    if (!$plans) {
      return;
    }

    // Account cancellation initializes a batch before invoking handlers. Add
    // matching handler operations to it so ordering does not depend on
    // workload size. Direct handler calls remain synchronous.
    if (batch_get()) {
      $this->scheduleBatch($entity_type, $plans);
      return;
    }

    foreach ($plans as $plan) {
      $this->executePlanSync($entity_type, $plan);
    }
  }

  /**
   * Schedules one batch operation per plan.
   */
  private function scheduleBatch(
    ContentEntityTypeInterface $entity_type,
    array $plans,
  ): void {
    $batch = (new BatchBuilder())
      ->setTitle(new TranslatableMarkup('Updating authored content'))
      ->setErrorMessage(new TranslatableMarkup(
        'An error occurred while updating authored content.',
      ))
      ->setFinishCallback([self::class, 'batchFinished']);
    $has_operations = FALSE;
    foreach ($plans as $plan) {
      if (!$this->hasMatches($entity_type, $plan)) {
        continue;
      }
      $batch->addOperation(
        [self::class, 'batchProcess'],
        [$entity_type->id(), $plan],
      );
      $has_operations = TRUE;
    }
    if ($has_operations) {
      batch_set($batch->toArray());
    }
  }

  /**
   * Batch operation callback.
   */
  public static function batchProcess(
    string $entity_type_id,
    array $plan,
    array &$context,
  ): void {
    /** @var self $processor */
    $processor = \Drupal::service(self::class);
    $entity_type = $processor->entityTypeManager
      ->getDefinition($entity_type_id);
    if (!$entity_type instanceof ContentEntityTypeInterface) {
      throw new \InvalidArgumentException(sprintf(
        'The %s account cancellation processor requires a content entity type.',
        $entity_type_id,
      ));
    }

    $sandbox = &$context['sandbox'];
    if (!isset($sandbox['cursor'])) {
      $sandbox['cursor'] = 0;
    }

    $processed = $processor->executePlanChunk(
      $entity_type,
      $plan,
      self::BATCH_CHUNK_SIZE,
      $sandbox['cursor'],
    );

    $context['finished'] = $processed < self::BATCH_CHUNK_SIZE ? 1 : 0.5;
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished(
    bool $success,
    array $results,
    array $operations,
  ): void {
    if (!$success) {
      \Drupal::messenger()->addError(new TranslatableMarkup(
        'Authored content could not be completely updated.',
      ));
    }
  }

  /**
   * Checks whether a plan has any work to schedule.
   */
  private function hasMatches(
    ContentEntityTypeInterface $entity_type,
    array $plan,
  ): bool {
    return (bool) $this->buildQuery($entity_type, $plan, 0)
      ->range(0, 1)
      ->execute();
  }

  /**
   * Iterates the plan synchronously.
   */
  private function executePlanSync(
    ContentEntityTypeInterface $entity_type,
    array $plan,
  ): void {
    $cursor = 0;
    while ($this->executePlanChunk(
      $entity_type,
      $plan,
      self::BATCH_CHUNK_SIZE,
      $cursor,
    ) > 0) {
      // Continue draining the plan.
    }
  }

  /**
   * Processes at most $chunk_size items and advances the cursor.
   */
  private function executePlanChunk(
    ContentEntityTypeInterface $entity_type,
    array $plan,
    int $chunk_size,
    int|string &$cursor,
  ): int {
    $query = $this->buildQuery($entity_type, $plan, $cursor);
    $query->range(0, $chunk_size);
    $ids = $query->execute();
    if (!$ids) {
      return 0;
    }
    $storage = $this->getStorage($entity_type);
    $all_revisions = $plan['all_revisions'] && $entity_type->isRevisionable();
    $count = 0;
    foreach ($ids as $result_key => $entity_id) {
      $id = $all_revisions ? $result_key : $entity_id;
      $entity = $all_revisions
        ? $storage->loadRevision($id)
        : $storage->load($id);
      if ($entity instanceof ContentEntityInterface) {
        $this->applyPlan($entity_type, $entity, $plan);
      }
      $cursor = $id;
      $count++;
    }
    return $count;
  }

  /**
   * Builds the base query for a plan with a cursor filter.
   */
  private function buildQuery(
    ContentEntityTypeInterface $entity_type,
    array $plan,
    int|string $cursor,
  ): QueryInterface {
    $storage = $this->getStorage($entity_type);
    $all_revisions = $plan['all_revisions'] && $entity_type->isRevisionable();
    $cursor_key = $all_revisions
      ? $entity_type->getKey('revision')
      : $entity_type->getKey('id');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($plan['field'], $plan['uid']);
    if ($all_revisions) {
      $query->allRevisions();
      if ($plan['operation'] === 'unpublish') {
        $query->condition(
          $entity_type->getRevisionMetadataKey('revision_default'),
          TRUE,
        );
      }
    }
    if ($cursor) {
      $query->condition($cursor_key, $cursor, '>');
    }
    $query->sort($cursor_key);
    return $query;
  }

  /**
   * Applies a plan operation to a single entity or revision.
   */
  private function applyPlan(
    ContentEntityTypeInterface $entity_type,
    ContentEntityInterface $entity,
    array $plan,
  ): void {
    $storage = $this->getStorage($entity_type);

    if ($plan['operation'] === 'delete_owned') {
      if (!$entity_type->isRevisionable() || $entity->isDefaultRevision()) {
        $storage->delete([$entity]);
      }
      else {
        $storage->deleteRevision($entity->getRevisionId());
      }
      return;
    }

    // Unpublishing only affects the canonical revision. Revision queries are
    // used to avoid workspace context substitution, so pending revisions must
    // be ignored after loading.
    if (
      $plan['operation'] === 'unpublish'
      && $entity_type->isRevisionable()
      && !$entity->isDefaultRevision()
    ) {
      return;
    }

    // Synchronization must be enabled before fields are changed. Computed
    // fields such as moderation_state may otherwise alter revision topology as
    // soon as their value is set, before the entity reaches presave hooks.
    $was_syncing = $entity->isSyncing();
    $entity->setSyncing(TRUE);
    try {
      if ($plan['operation'] === 'unpublish') {
        foreach ($this->unpublishEntity(
          $entity_type,
          $entity,
          $plan['uid'],
        ) as $translation) {
          // Content Moderation persists the moderation state of the active
          // translation only, so each affected translation must be active when
          // it is saved.
          $storage->save($translation);
        }
        return;
      }

      $changed = match ($plan['operation']) {
        'reassign' => $this->reassignEntity(
          $entity_type,
          $entity,
          $plan['uid'],
          $plan['reassignment_values'],
        ),
        'anonymize_revision' => $this->replaceUserReference(
          $entity,
          $entity_type->getRevisionMetadataKey('revision_user'),
          $plan['uid'],
        ),
        default => FALSE,
      };

      if ($changed) {
        $storage->save($entity);
      }
    }
    finally {
      $entity->setSyncing($was_syncing);
    }
  }

  /**
   * Reassigns ownership fields on a single entity.
   */
  private function reassignEntity(
    ContentEntityTypeInterface $entity_type,
    ContentEntityInterface $entity,
    int $uid,
    array $reassignment_values,
  ): bool {
    $owner_field = $entity_type->getKey('owner');
    $changed = FALSE;
    if ($owner_field) {
      // Additional values such as the stored comment author name must only be
      // changed on translations that are actually being reassigned.
      if ($reassignment_values) {
        $changed = $this->setReassignmentValues(
          $entity,
          $reassignment_values,
          $owner_field,
          $uid,
        );
      }
      $changed = $this->replaceUserReference(
        $entity,
        $owner_field,
        $uid,
      ) || $changed;
    }
    $revision_user_field = $entity_type
      ->getRevisionMetadataKey('revision_user');
    if ($revision_user_field && $revision_user_field !== $owner_field) {
      $changed = $this->replaceUserReference(
        $entity,
        $revision_user_field,
        $uid,
      ) || $changed;
    }
    return $changed;
  }

  /**
   * Unpublishes owned translations of the current entity revision.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The affected translations.
   */
  private function unpublishEntity(
    ContentEntityTypeInterface $entity_type,
    ContentEntityInterface $entity,
    int $uid,
  ): array {
    $published_field = $entity_type->getKey('published');
    $owner_field = $entity_type->getKey('owner');
    if (!$published_field || !$owner_field) {
      return [];
    }
    $changed = [];
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      if (
        $translation->hasField($owner_field)
        && (int) $translation->get($owner_field)->target_id === $uid
        && !$translation->get($published_field)->isEmpty()
        && (bool) $translation->get($published_field)->value
      ) {
        $this->eventDispatcher->dispatch(
          new EntityCancellationUnpublishEvent($translation),
        );
        $translation->set($published_field, FALSE);
        $changed[$langcode] = $translation;
      }
    }
    return $changed;
  }

  /**
   * Replaces a user reference with anonymous on every matching translation.
   */
  private function replaceUserReference(
    ContentEntityInterface $entity,
    string $field_name,
    int $uid,
  ): bool {
    $changed = FALSE;
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      if (
        $translation->hasField($field_name)
        && (int) $translation->get($field_name)->target_id === $uid
      ) {
        $translation->set($field_name, 0);
        // Keep the anonymous entity in the computed property during presave so
        // that entity types which default an empty revision author to the new
        // owner do not replace UID 0 before it reaches storage.
        $field_type = $translation->get($field_name)
          ->getFieldDefinition()
          ->getType();
        if ($field_type === 'entity_reference') {
          $translation->get($field_name)->entity = User::getAnonymousUser();
        }
        $changed = TRUE;
      }
    }
    return $changed;
  }

  /**
   * Sets additional values on translations owned by the cancelled account.
   */
  private function setReassignmentValues(
    ContentEntityInterface $entity,
    array $values,
    string $owner_field,
    int $uid,
  ): bool {
    $changed = FALSE;
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $translation = $entity->getTranslation($langcode);
      if (
        !$translation->hasField($owner_field)
        || (int) $translation->get($owner_field)->target_id !== $uid
      ) {
        continue;
      }
      foreach ($values as $field_name => $value) {
        if ($translation->hasField($field_name)) {
          $translation->set($field_name, $value);
          $changed = TRUE;
        }
      }
    }
    return $changed;
  }

  /**
   * Gets content entity storage for an entity type.
   */
  private function getStorage(
    ContentEntityTypeInterface $entity_type,
  ): ContentEntityStorageInterface {
    $storage = $this->entityTypeManager->getStorage($entity_type->id());
    if (!$storage instanceof ContentEntityStorageInterface) {
      throw new \InvalidArgumentException(sprintf(
        'The %s account cancellation processor requires content entity ' .
        'storage.',
        $entity_type->id(),
      ));
    }
    return $storage;
  }

}
