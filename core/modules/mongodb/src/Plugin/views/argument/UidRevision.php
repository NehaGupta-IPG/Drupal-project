<?php

namespace Drupal\mongodb\Plugin\views\argument;

use Drupal\node\Plugin\views\argument\UidRevision as NodeUidRevision;

/**
 * Overriding the views argument plugin "node_uid_revision".
 */
class UidRevision extends NodeUidRevision {

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    foreach ($this->value as &$value) {
      $value = (int) $value;
    }
    $or_condition = $this->query->getConnection()->condition('OR');
    $or_condition->condition('node_current_revision.uid', $this->value, 'IN');
    $or_condition->condition('node_all_revisions.revision_uid', $this->value, 'IN');
    $this->query->addCondition(0, $or_condition);
  }

}
