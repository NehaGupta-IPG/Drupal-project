<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\node\Plugin\views\filter\Status as CoreStatus;

/**
 * Overriding the views filter plugin "node_status".
 */
class Status extends CoreStatus {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $table = $this->ensureMyTable();
    $field_uid = 'node_current_revision.uid';
    $field_status = 'node_current_revision.status';
    if ($table != $this->view->storage->get('base_table')) {
      $field_uid = $table . '.' . $field_uid;
      $field_status = $table . '.' . $field_status;
    }

    $and_condition = $this->query->getConnection()->condition('AND');
    $and_condition->condition($field_uid, '***CURRENT_USER***');
    $and_condition->condition('***CURRENT_USER***', 0, '<>');
    $and_condition->condition('***VIEW_OWN_UNPUBLISHED_NODES***', 1);

    $or_condition = $this->query->getConnection()->condition('OR');
    $or_condition->condition($and_condition);
    $or_condition->condition($field_status, TRUE);
    $or_condition->condition('***BYPASS_NODE_ACCESS***', 1);
    if ($this->moduleHandler->moduleExists('content_moderation')) {
      $or_condition->condition('***VIEW_ANY_UNPUBLISHED_NODES***', 1);
    }

    $this->query->addCondition($this->options['group'], $or_condition);
  }

}
