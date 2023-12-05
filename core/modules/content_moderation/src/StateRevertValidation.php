<?php

namespace Drupal\content_moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;

/**
 * The default permissions based implementation of reverting revisions.
 */
class StateRevertValidation implements StateRevertValidationInterface {

  /**
   * Constructs a new StateRevertValidation.
   */
  public function __construct(protected ModerationInformationInterface $moderationInfo) {
  }

  /**
   * {@inheritdoc}
   */
  public function getValidRevertStates(ContentEntityInterface $revision, AccountInterface $user): array {
    $workflow = $this->moderationInfo->getWorkflowForEntity($revision);
    return array_filter($workflow->getTypePlugin()->getStates(), function (StateInterface $state) use ($workflow, $user) {
      return $user->hasPermission('revert ' . $workflow->id() . ' revisions to ' . $state->id());
    });
  }

}
