<?php

namespace Drupal\content_moderation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Form\NodeRevisionRevertForm;
use Drupal\node\NodeInterface;
use Drupal\workflows\State;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Content moderation-specific revision revert form.
 */
class RevisionRevertForm extends NodeRevisionRevertForm {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The transition validation service.
   *
   * @var \Drupal\content_moderation\StateRevertValidationInterface
   */
  protected $validation;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moderationInformation = $container->get('content_moderation.moderation_information');
    $instance->validation = $container->get('content_moderation.state_revert_validation');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node_revision = NULL) {
    $form = parent::buildForm($form, $form_state, $node_revision);

    if (!$this->moderationInformation->isModeratedEntity($this->revision)) {
      return $form;
    }

    // Only add the option to select a target state if the user has access to
    // revert to at least one state.
    $target_states = $this->validation->getValidRevertStates($this->revision, $this->currentUser());
    if (empty($target_states)) {
      return $form;
    }

    $workflow = $this->moderationInformation->getWorkflowForEntity($this->revision);
    $form['current'] = [
      '#type' => 'item',
      '#title' => $this->t('Revision moderation state'),
      '#markup' => $workflow->getTypePlugin()->getState($this->revision->moderation_state->value)->label(),
    ];
    $form['revert_state'] = [
      '#type' => 'select',
      '#title' => $this->t('Revert and set to'),
      '#options' => array_map([State::class, 'labelCallback'], $target_states),
      '#default_value' => $this->revision->moderation_state->value,
      '#description' => $this->t('Select the moderation state that will be assigned to the content revision once it has been reverted. The state selected here may either immediately publish/unpublish content or create a pending revision that may continue to be edited.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareRevertedRevision(NodeInterface $revision, FormStateInterface $form_state) {
    $revision = parent::prepareRevertedRevision($revision, $form_state);
    if ($form_state->hasValue('revert_state')) {
      $revision->moderation_state = $form_state->getValue('revert_state');
    }
    return $this->nodeStorage->createRevision($revision);
  }

}
