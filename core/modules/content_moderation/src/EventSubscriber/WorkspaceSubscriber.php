<?php

namespace Drupal\content_moderation\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\content_moderation\ContentModerationState;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\workspaces\Event\WorkspacePrePublishEvent;
use Drupal\workspaces\Event\WorkspacePublishEvent;
use Drupal\workspaces\WorkspaceTrackerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks whether a workspace is publishable, and prevents publishing if needed.
 */
class WorkspaceSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new WorkspaceSubscriber instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\workspaces\WorkspaceTrackerInterface|null $workspaceTracker
   *   The workspace tracker service.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ?WorkspaceTrackerInterface $workspaceTracker,
  ) {}

  /**
   * Prevents a workspace from being published based on certain conditions.
   *
   * @param \Drupal\workspaces\Event\WorkspacePublishEvent $event
   *   The workspace publish event.
   */
  public function onWorkspacePrePublish(WorkspacePublishEvent $event): void {
    // Prevent a workspace from being published if there are any pending
    // revisions in a moderation state that doesn't create default revisions.
    $workspace = $event->getWorkspace();

    $tracked_revisions = $this->workspaceTracker->getTrackedEntities($workspace->id());

    // Gather a list of moderation states that don't create a default revision.
    $workflow_non_default_states = [];
    foreach ($this->entityTypeManager->getStorage('workflow')->loadByProperties(['type' => 'content_moderation']) as $workflow) {
      /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $workflow_type */
      $workflow_type = $workflow->getTypePlugin();
      // Find all workflows which are moderating entity types of the same type
      // to those that are tracked by the workspace.
      if (array_intersect($workflow_type->getEntityTypes(), array_keys($tracked_revisions))) {
        $workflow_non_default_states[$workflow->id()] = array_filter(array_map(function (ContentModerationState $state) {
          return !$state->isDefaultRevisionState() ? $state->id() : NULL;
        }, $workflow_type->getStates()));
      }
    }

    // If no tracked revisions are moderated then no status check is necessary.
    if (empty($workflow_non_default_states)) {
      return;
    }

    // Check if any revisions that are about to be published are in a
    // non-default revision moderation state.
    $query = $this->entityTypeManager->getStorage('content_moderation_state')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE);

    $tracked_revisions_condition_group = $query->orConditionGroup();
    foreach ($tracked_revisions as $tracked_type => $tracked_revision_ids) {
      $entity_type_group = $query->andConditionGroup()
        ->condition('content_entity_type_id', $tracked_type)
        ->condition('content_entity_revision_id', array_keys($tracked_revision_ids), 'IN');

      $tracked_revisions_condition_group->condition($entity_type_group);
    }
    $query->condition($tracked_revisions_condition_group);

    $workflow_condition_group = $query->orConditionGroup();
    foreach ($workflow_non_default_states as $workflow_id => $non_default_states) {
      $group = $query->andConditionGroup()
        ->condition('workflow', $workflow_id, '=')
        ->condition('moderation_state', $non_default_states, 'IN');

      $workflow_condition_group->condition($group);
    }
    $query->condition($workflow_condition_group);

    $result_ids = $query->execute();
    if ($count = count($result_ids)) {
      $message = \Drupal::translation()->formatPlural($count, 'The @label workspace can not be published because it contains 1 item in an unpublished moderation state.', 'The @label workspace can not be published because it contains @count items in an unpublished moderation state.', [
        '@label' => $workspace->label(),
      ]);

      $items = [];
      $moderation_state_revisions = $this->entityTypeManager->getStorage('content_moderation_state')->loadMultipleRevisions(array_keys($result_ids));
      foreach ($moderation_state_revisions as $moderation_state) {
        $entity_type_id = $moderation_state->content_entity_type_id->value;
        $entity_id = $moderation_state->content_entity_id->value;
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
        if ($entity) {
          try {
            $url = $entity->toUrl()->toString();
            $items[] = '<a href="' . $url . '">' . Html::escape($entity->label()) . '</a>';
          }
          catch (\Exception) {
            $items[] = Html::escape($entity->label());
          }
        }
      }

      $full_message = (string) $message;
      if (count($items) === 1) {
        $full_message = rtrim($full_message, '.') . ': ' . $items[0];
      }
      elseif (count($items) > 1) {
        $full_message .= '<ul>' . implode('', array_map(fn($item) => '<li>' . $item . '</li>', $items)) . '</ul>';
      }

      $event->stopPublishing();
      $event->setPublishingStoppedReason(Markup::create($full_message));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[WorkspacePrePublishEvent::class][] = ['onWorkspacePrePublish'];
    return $events;
  }

}
