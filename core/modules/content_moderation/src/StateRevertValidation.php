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
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Constructs a new StateRevertValidation.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   */
  public function __construct(ModerationInformationInterface $moderation_info) {
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidRevertStates(ContentEntityInterface $revision, AccountInterface $user) {
    $workflow = $this->moderationInfo->getWorkflowForEntity($revision);
    return array_filter($workflow->getTypePlugin()->getStates(), function (StateInterface $state) use ($workflow, $user) {
      return $user->hasPermission('revert ' . $workflow->id() . ' revisions to ' . $state->id());
    });
  }

}
