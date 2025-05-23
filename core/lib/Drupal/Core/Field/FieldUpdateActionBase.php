<?php

namespace Drupal\Core\Field;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Action\ActionResult;

/**
 * Provides a base for action plugins that update one or more fields.
 *
 * Example implementation:
 *
 * @code
 * class PromoteAndMakeSticky extends FieldUpdateActionBase {
 *
 *   protected function getFieldsToUpdate() {
 *     return [
 *       'status' => NODE_PROMOTED,
 *       'sticky' => NODE_STICKY,
 *     ];
 *   }
 *
 * }
 * @endcode
 *
 * @see \Drupal\Core\Action\Plugin\Action\PublishAction
 */
abstract class FieldUpdateActionBase extends ActionBase {

  /**
   * Gets an array of values to be set.
   *
   * @return array
   *   Array of values with field names as keys.
   */
  abstract protected function getFieldsToUpdate();

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ActionResult {
    // @var \Drupal\Core\Entity\EntityInterface $entity
    [$entity] = $values;
    foreach ($this->getFieldsToUpdate() as $field => $value) {
      $entity->$field = $value;
    }
    $entity->save();
    return ActionResult::success($this->t('The @type %label has been updated.', [
      '@type' => $entity->getEntityType()->getLabel(),
      '%label' => $entity->label(),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $values = $this->getContextValues();
    $entity = reset($values);
    /** @var \Drupal\Core\Access\AccessResultInterface $result */
    $result = $entity->access('update', $account, TRUE);

    foreach ($this->getFieldsToUpdate() as $field => $value) {
      $result = $result->andIf($entity->{$field}->access('edit', $account, TRUE));
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

}
