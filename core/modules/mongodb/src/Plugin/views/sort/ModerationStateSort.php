<?php

namespace Drupal\mongodb\Plugin\views\sort;

use Drupal\content_moderation\Plugin\views\sort\ModerationStateSort as CoreModerationStateSort;
use Drupal\mongodb\Plugin\views\ModerationStateJoinViewsHandlerTrait;

/**
 * Overrides the views sort plugin "moderation_state_sort".
 */
class ModerationStateSort extends CoreModerationStateSort {

  use ModerationStateJoinViewsHandlerTrait;

  /**
   * Called to add the sort to a query.
   */
  public function query() {
    $this->ensureMyTable();

    $table_mapping = $this->entityTypeManager->getStorage('content_moderation_state')->getTableMapping();

    // Get the correct embedded table to use in the sort.
    if (!empty($this->query->getAllRevisionsTable()) && !empty($this->query->getLatestRevisionTable())) {
      if ($this->query->hasLatestRevisionFilter()) {
        $revision_table = $table_mapping->getJsonStorageLatestRevisionTable();
      }
      else {
        $revision_table = $table_mapping->getJsonStorageAllRevisionsTable();
      }
    }
    else {
      $revision_table = $table_mapping->getJsonStorageCurrentRevisionTable();
    }

    // Add the field.
    $this->query->addOrderBy($this->tableAlias, "$revision_table.{$this->realField}", $this->options['order']);

  }

}
