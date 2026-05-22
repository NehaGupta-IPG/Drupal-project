<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\mongodb\Plugin\views\ModerationStateJoinViewsHandlerTrait;

/**
 * Overriding the views field plugin "moderation_state_field".
 */
class ModerationStateField extends EntityField {

  use ModerationStateJoinViewsHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function clickSort($order) {
    $this->ensureMyTable();

    // This could be derived from the content_moderation_state entity table
    // mapping, however this is an internal entity type whose storage should
    // remain constant.
    $storage = $this->entityTypeManager->getStorage('content_moderation_state');
    $table_mapping = $storage->getTableMapping();
    $storage_definition = $this->entityFieldManager->getActiveFieldStorageDefinitions('content_moderation_state')['moderation_state'];
    $column_name = $table_mapping->getFieldColumnName($storage_definition, 'value');

    // Get the correct embedded table to use in the order by.
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

    $this->aliases[$column_name] = "{$this->tableAlias}.$revision_table.$column_name";

    $this->query->addFilterUnwindPath("{$this->tableAlias}.$revision_table");

    $this->query->addOrderBy(NULL, NULL, $order, $this->aliases[$column_name]);
  }

}
