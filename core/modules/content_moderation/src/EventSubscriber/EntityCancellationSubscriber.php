<?php

declare(strict_types=1);

namespace Drupal\content_moderation\EventSubscriber;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\user\Event\EntityCancellationUnpublishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps moderation state consistent when account content is unpublished.
 *
 * @internal
 *   Event subscribers are internal.
 */
final class EntityCancellationSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ModerationInformationInterface $moderationInformation,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [EntityCancellationUnpublishEvent::class => 'onUnpublish'];
  }

  /**
   * Moves moderated content to its required unpublished state.
   *
   * @param \Drupal\user\Event\EntityCancellationUnpublishEvent $event
   *   The entity cancellation unpublish event.
   */
  public function onUnpublish(EntityCancellationUnpublishEvent $event): void {
    $entity = $event->entity;
    if (
      !$this->moderationInformation->isModeratedEntity($entity)
      || !$entity->hasField('moderation_state')
    ) {
      return;
    }

    $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
    if ($workflow === NULL) {
      return;
    }
    // Every Content Moderation workflow requires the canonical draft state.
    $entity->set('moderation_state', 'draft');
  }

}
