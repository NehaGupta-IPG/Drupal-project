<?php

declare(strict_types=1);

namespace Drupal\workflows;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\Plugin\Field\FieldType\WorkflowStateItem;

/**
 * Defines an item list class for the 'workflow_state' field type.
 */
class WorkflowStateFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public static function processDefaultValue($default_value, FieldableEntityInterface $entity, FieldDefinitionInterface $definition) {
    // The default value of a workflow state field can only be the initial state
    // of the workflow.
    $workflow_id = WorkflowStateItem::resolveWorkflowId($definition, $entity);
    if (!$workflow_id) {
      return [];
    }

    $workflow = Workflow::load($workflow_id);
    if (!$workflow) {
      return [];
    }

    return [['value' => $workflow->getTypePlugin()->getInitialState()->id()]];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultValuesForm(array &$form, FormStateInterface $form_state) {
    $item = $this->first();
    if (!$item instanceof WorkflowStateItemInterface) {
      return [];
    }

    $label = $item->getStateLabel();
    if ($label === '') {
      return [];
    }

    return [
      '#plain_text' => $label,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultValueWidget(FormStateInterface $form_state) {
    return NULL;
  }

}
