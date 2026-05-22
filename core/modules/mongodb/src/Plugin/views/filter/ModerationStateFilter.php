<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\content_moderation\Plugin\views\filter\ModerationStateFilter as CoreModerationStateFilter;
use Drupal\mongodb\Plugin\views\ModerationStateJoinViewsHandlerTrait;

/**
 * Overriding the views filter plugin "moderation_state_filter".
 */
class ModerationStateFilter extends CoreModerationStateFilter {

  use ModerationStateJoinViewsHandlerTrait;
  use InOperatorTrait;

  /**
   * {@inheritdoc}
   */
  protected function opSimple() {
    if (empty($this->value)) {
      return;
    }

    $this->ensureMyTable();

    $entity_type = $this->entityTypeManager->getDefinition($this->getEntityType());
    $bundle_condition = NULL;

    // Set the default embedded revision table.
    $revision_table = 'content_moderation_state_current_revision';

    if ($entity_type->hasKey('bundle')) {
      // Get a list of bundles that are being moderated by the workflows
      // configured in this filter.
      $workflow_ids = $this->getWorkflowIds();
      $moderated_bundles = [];
      foreach ($this->bundleInfo->getBundleInfo($this->getEntityType()) as $bundle_id => $bundle) {
        if (isset($bundle['workflow']) && in_array($bundle['workflow'], $workflow_ids, TRUE)) {
          $moderated_bundles[] = $bundle_id;
        }
      }

      // If we have a list of moderated bundles, restrict the query to show only
      // entities in those bundles.
      if ($moderated_bundles) {
        $entity_base_table_alias = $this->relationship ?: $this->table;

        $table_mapping = $this->entityTypeManager->getStorage($entity_type->id())->getTableMapping();
        // Get the correct embedded table to use in the condition.
        if (!empty($this->query->getAllRevisionsTable()) && !empty($this->query->getLatestRevisionTable())) {
          if ($this->query->hasLatestRevisionFilter()) {
            $left_revision_table = $table_mapping->getJsonStorageLatestRevisionTable();
            $revision_table = 'content_moderation_state_latest_revision';
          }
          else {
            $left_revision_table = $table_mapping->getJsonStorageAllRevisionsTable();
            $revision_table = 'content_moderation_state_all_revisions';
          }
        }
        else {
          $left_revision_table = $table_mapping->getJsonStorageCurrentRevisionTable();
        }

        $bundle_field = "$entity_base_table_alias.$left_revision_table.{$entity_type->getKey('bundle')}";

        $bundle_condition = $this->view->query->getConnection()->condition('AND');
        $bundle_condition->condition($bundle_field, $moderated_bundles, 'IN');
      }
      // Otherwise, force the query to return an empty result.
      else {
        $this->query->addCondition($this->options['group'], $this->view->query->getConnection()->condition('AND')->alwaysFalse());
        return;
      }
    }

    if ($this->operator === 'in') {
      $operator = "=";
    }
    else {
      $operator = "<>";
    }

    // The values are strings composed from the workflow ID and the state ID, so
    // we need to create a complex WHERE condition.
    $field = $this->view->query->getConnection()->condition('OR');
    foreach ((array) $this->value as $value) {
      [$workflow_id, $state_id] = explode('-', $value, 2);

      $and = $this->view->query->getConnection()->condition('AND');
      $and
        ->condition("$this->tableAlias.$revision_table.workflow", $workflow_id, '=')
        ->condition("$this->tableAlias.$revision_table.$this->realField", $state_id, $operator);

      $field->condition($and);
    }

    // Unwind the correct embedded revision table.
    $this->query->addFilterUnwindPath("$this->tableAlias.$revision_table");

    if ($bundle_condition) {
      // The query must match the bundle AND the workflow/state conditions.
      $bundle_condition->condition($field);
      $this->query->addCondition($this->options['group'], $bundle_condition);
    }
    else {
      $this->query->addCondition($this->options['group'], $field);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function opEmpty() {
    $this->ensureMyTable();
    if ($this->operator == 'empty') {
      $operator = "IS NULL";
    }
    else {
      $operator = "IS NOT NULL";
    }

    $this->query->addCondition($this->options['group'], 'content_moderation_state.content_moderation_state_current_revision.' . $this->mongodbField, NULL, $operator);
  }

  /**
   * Gets the list of Workflow IDs configured for this filter.
   *
   * @return array
   *   And array of workflow IDs.
   */
  protected function getWorkflowIds() {
    $workflow_ids = [];
    foreach ((array) $this->value as $value) {
      [$workflow_id] = explode('-', $value, 2);
      $workflow_ids[] = $workflow_id;
    }

    return array_unique($workflow_ids);
  }

}
