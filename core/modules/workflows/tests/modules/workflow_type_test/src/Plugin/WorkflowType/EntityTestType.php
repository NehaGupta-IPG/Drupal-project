<?php

declare(strict_types=1);

namespace Drupal\workflow_type_test\Plugin\WorkflowType;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\workflows\Attribute\WorkflowType;
use Drupal\workflows\EntityWorkflowTypeInterface;
use Drupal\workflows\Plugin\WorkflowTypeBase;
use Drupal\workflows\TransitionInterface;

/**
 * Test workflow type that implements EntityWorkflowTypeInterface.
 */
#[WorkflowType(
  id: 'workflow_type_entity_test',
  label: new TranslatableMarkup('Entity Test')
)]
class EntityTestType extends WorkflowTypeBase implements EntityWorkflowTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function checkTransitionAccess(TransitionInterface $transition, AccountInterface $account, FieldableEntityInterface $entity, string $field_name): AccessResultInterface {
    $blocked = \Drupal::keyValue('workflow_type_entity_test')->get('blocked_transitions', []);
    if (in_array($transition->id(), $blocked, TRUE)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

}
