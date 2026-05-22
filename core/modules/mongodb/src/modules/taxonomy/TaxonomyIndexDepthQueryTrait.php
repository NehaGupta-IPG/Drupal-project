<?php

namespace Drupal\mongodb\modules\taxonomy;

/**
 * Builds a performant depth subquery and adds it as a join to the query.
 */
trait TaxonomyIndexDepthQueryTrait {

  /**
   * Builds a performant depth subquery and adds it as a join to the query.
   *
   * @param string|array $tids
   *   The terms ID(s) to do a depth search for.
   */
  protected function addSubQueryJoin($tids): void {
    if (is_array($tids)) {
      foreach ($tids as &$tid) {
        $tid = (int) $tid;
      }
    }
    else {
      $tids = (int) $tids;
    }

    $connection = $this->query->getConnection();
    $operator = is_array($tids) ? 'IN' : '=';

    // Get the level 0 node IDs.
    $subquery = $connection->select('taxonomy_index', 'tn');
    $subquery->addField('tn', 'nid');
    $subquery->condition('tn.tid', $tids, $operator);
    $nids = $subquery->execute()->fetchCol();
    if (!$nids) {
      $nids = [];
    }

    if ($this->options['depth'] !== 0) {
      // Set $left_field and $right_field depending on whether we are traversing
      // up or down the hierarchy.
      if ($this->options['depth'] > 0) {
        $left_field = 'parent_target_id';
        $right_field = 'entity_id';
      }
      else {
        $left_field = 'entity_id';
        $right_field = 'parent_target_id';
      }
      // Traverse the hierarchy to check the child or parent terms.
      foreach (range(1, abs($this->options['depth'])) as $count) {
        $union_query = $connection->select('taxonomy_index', 'tn');
        $union_query->addField('tn', 'nid');
        $left_join = "tn.tid";
        if ($this->options['depth'] > 0) {
          $union_query->addJoin('INNER', 'taxonomy_term_data', "th", $union_query->joinCondition()->compare($left_join, 'th.taxonomy_term_current_revision.taxonomy_term_current_revision__parent.entity_id'));
          $left_join = "th.taxonomy_term_current_revision.taxonomy_term_current_revision__parent.$left_field";
        }
        foreach (range(1, $count) as $inner_count) {
          $union_query->addJoin('INNER', 'taxonomy_term_data', "th$inner_count", $union_query->joinCondition()->compare($left_join, "th$inner_count.taxonomy_term_current_revision.taxonomy_term_current_revision__parent.$right_field"));
          $left_join = "th$inner_count.taxonomy_term_current_revision.taxonomy_term_current_revision__parent.$left_field";
        }
        $union_query->addFilterUnwindPath("th$inner_count.taxonomy_term_current_revision.taxonomy_term_current_revision__parent");
        $union_query->condition("th$inner_count.taxonomy_term_current_revision.taxonomy_term_current_revision__parent.entity_id", $tids, $operator);

        // Get the level >=1 node IDs.
        // Each level get its own query.
        $new_nids = $union_query->execute()->fetchCol();
        if ($new_nids) {
          $nids = array_merge($nids, $new_nids);
        }
      }
    }

    // Remove duplicate node IDs and make them all integer values.
    $nids = array_unique($nids);
    foreach ($nids as &$nid) {
      $nid = (int) $nid;
    }

    // Add the Node ID's to the main query.
    if (!empty($nids)) {
      $condition = $this->view->getDatabaseCondition('AND');
      $condition->condition("$this->tableAlias.$this->realField", $nids, 'IN');
      $this->query->addCondition(3, $condition);
    }
  }

}
