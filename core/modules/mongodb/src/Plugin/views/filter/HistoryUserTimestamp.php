<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\history\Plugin\views\filter\HistoryUserTimestamp as CoreHistoryUserTimestamp;
use MongoDB\BSON\UTCDateTime;

// cspell:ignore fieldcompare

/**
 * Overriding the views filter plugin "history_user_timestamp".
 */
class HistoryUserTimestamp extends CoreHistoryUserTimestamp {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This can only work if we're authenticated in.
    if (!\Drupal::currentUser()->isAuthenticated()) {
      return;
    }

    // Don't filter if we're exposed and the checkbox isn't selected.
    if ((!empty($this->options['exposed'])) && empty($this->value)) {
      return;
    }

    // Hey, Drupal kills old history, so nodes that haven't been updated
    // since HISTORY_READ_LIMIT are outta here!
    $limit = $this->time->getRequestTime() - HISTORY_READ_LIMIT;

    $this->ensureMyTable();
    $field = "$this->tableAlias.$this->realField";
    $time = new UTCDateTime($limit * 1000);
    $ces = $this->query->ensureTable('comment_entity_statistics', $this->relationship);
    if ($ces) {
      $or_condition1 = $this->query->getConnection()->condition('OR');
      $or_condition1->condition('node_current_revision.changed', $time, '>');
      $or_condition1->condition("$ces.last_comment_timestamp", $time, '>');

      $and_condition = $this->view->getDatabaseCondition('AND');
      $and_condition->isNull($field);
      $and_condition->condition($or_condition1);

      $or_condition2 = $this->query->getConnection()->condition('OR');
      $or_condition2->condition($field, ['field' => 'node_current_revision.changed', 'operator' => '<'], 'FIELDCOMPARE');
      $or_condition2->condition($field, ['field' => "$ces.last_comment_timestamp", 'operator' => '<'], 'FIELDCOMPARE');
      $or_condition2->condition($and_condition);

      $this->query->addFilterUnwindPath('history');
      $this->query->addFilterUnwindPath($ces);
    }
    else {
      $and_condition = $this->query->getConnection()->condition('AND');
      $and_condition->isNull($field);
      $and_condition->condition('node_current_revision.changed', $time, '>');

      $or_condition = $this->query->getConnection()->condition('OR');
      $or_condition->condition($field, ['field' => 'node_current_revision.changed', 'operator' => '<'], 'FIELDCOMPARE');
      $or_condition->condition($and_condition);
      $this->query->addFilterUnwindPath('history');
    }
    $this->query->addCondition($this->options['group'], $or_condition);
  }

}
