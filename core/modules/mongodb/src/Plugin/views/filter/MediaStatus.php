<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\media\Plugin\views\filter\Status;

/**
 * Overriding the views filter plugin "media_status".
 */
class MediaStatus extends Status {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();

    $and_condition = $this->query->getConnection()->condition('AND');
    $and_condition->condition('media_current_revision.uid', '***CURRENT_USER***');
    $and_condition->condition('***CURRENT_USER***', 0, '<>');
    $and_condition->condition('***VIEW_OWN_UNPUBLISHED_NODES***', 1);

    $or_condition = $this->query->getConnection()->condition('OR');
    $or_condition->condition($and_condition);
    $or_condition->condition('media_current_revision.status', TRUE);
    $or_condition->condition('***ADMINISTER_MEDIA***', 1);
    if ($this->moduleHandler->moduleExists('content_moderation')) {
      $or_condition->condition('***VIEW_ANY_UNPUBLISHED_NODES***', 1);
    }

    $this->query->addCondition($this->options['group'], $or_condition);

  }

}
