<?php

/**
 * @file
 * API documentation for Workflows module.
 */

/**
 * @defgroup workflow_type_plugins Workflow Type Plugins
 * @{
 * Any module harnessing the Workflows module must define a Workflow Type
 * Plugin. This allows the module to tailor the workflow to its specific need.
 * For example, the Content Moderation module uses its Workflow Type Plugin to
 * link workflows to entities.
 * On their own, workflows are a stand-alone concept. It takes a module such as
 * Content Moderation to give the workflow context.
 * @}
 */

/**
 * @defgroup workflow_transition_hooks Workflow Transition Hooks
 * @{
 * When a workflow is attached to a fieldable entity via the 'workflow_state'
 * field type, two transition hooks are invoked, allowing modules to react
 * before and after the transition has been performed.
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Acts before a workflow transition is performed.
 *
 * This hook is invoked before the new state is written to the storage.
 *
 * @param \Drupal\workflows\WorkflowInterface $workflow
 *   The workflow.
 * @param \Drupal\workflows\TransitionInterface $transition
 *   The transition being applied.
 * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
 *   The entity for which a workflow state is changed.
 * @param string $field_name
 *   The name of the field to which the workflow is attached.
 */
function hook_workflows_pre_transition(\Drupal\workflows\WorkflowInterface $workflow, \Drupal\workflows\TransitionInterface $transition, \Drupal\Core\Entity\FieldableEntityInterface $entity, string $field_name) {
}

/**
 * Acts after a workflow transition is performed.
 *
 * This hook is invoked after the new state is written to the storage.
 *
 * @param \Drupal\workflows\WorkflowInterface $workflow
 *   The workflow.
 * @param \Drupal\workflows\TransitionInterface $transition
 *   The transition that was applied.
 * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
 *   The entity for which a workflow state was changed.
 * @param string $field_name
 *   The name of the field to which the workflow is attached.
 */
function hook_workflows_post_transition(\Drupal\workflows\WorkflowInterface $workflow, \Drupal\workflows\TransitionInterface $transition, \Drupal\Core\Entity\FieldableEntityInterface $entity, string $field_name) {
}

/**
 * @} End of "addtogroup hooks".
 */
