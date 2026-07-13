<?php

namespace Drupal\taxonomy;

/**
 * Expands term IDs by depth in PHP and joins the result to the query.
 *
 * This is an alternative strategy to
 * \Drupal\taxonomy\TaxonomyIndexDepthQueryTrait. Instead of traversing the
 * term hierarchy in SQL with a union of joins on taxonomy_term__parent, the
 * hierarchy is expanded first with one small query on taxonomy_term__parent
 * per hierarchy level, each only touching the terms in the subtree. The
 * resulting flat list of term IDs is used in a single subquery on
 * taxonomy_index which is joined to the main views query using an INNER
 * JOIN.
 *
 * For example, if the $tids value is '718' and depth is configured to 2, the
 * resulting JOIN to node_field_data will be:
 * @code
 * INNER JOIN (SELECT DISTINCT tn.nid AS nid
 * FROM
 * taxonomy_index tn
 * WHERE tn.tid IN ('718', '719', '720')) taxonomy_index_depth_join
 * ON node_field_data.nid = taxonomy_index_depth_join.nid
 * @endcode
 */
trait TaxonomyIndexDepthJoinQueryTrait {

  /**
   * Builds a term list subquery and adds it as a join to the query.
   *
   * @param string|int|array $tids
   *   The term ID(s) to do a depth search for.
   */
  protected function addDepthJoinToQuery($tids): void {
    $tids = array_map('intval', (array) $tids);
    $depth = (int) $this->options['depth'];
    $connection = $this->query->getConnection();

    if ($depth !== 0) {
      // Select the source or the target of the parent reference depending on
      // whether we are traversing down or up the hierarchy.
      if ($depth > 0) {
        $field = 'entity_id';
        $condition = 'parent_target_id';
      }
      else {
        $field = 'parent_target_id';
        $condition = 'entity_id';
      }
      // Traverse the hierarchy one level per query. Terms can have multiple
      // parents, so track the terms already seen to only walk shared
      // subtrees once.
      $seen = array_flip($tids);
      $current = $tids;
      foreach (range(1, abs($depth)) as $level) {
        if (!$current) {
          break;
        }
        $found = $connection->select('taxonomy_term__parent', 'p')
          ->fields('p', [$field])
          ->condition("p.$condition", $current, 'IN')
          ->condition("p.$field", 0, '<>')
          ->execute()
          ->fetchCol();
        $current = [];
        foreach ($found as $tid) {
          $tid = (int) $tid;
          if (!isset($seen[$tid])) {
            $seen[$tid] = TRUE;
            $current[] = $tid;
            $tids[] = $tid;
          }
        }
      }
    }

    // Build a subquery on the taxonomy index. DISTINCT ensures nodes tagged
    // with multiple terms in the hierarchy are not duplicated in the result.
    $subquery = $connection->select('taxonomy_index', 'tn');
    $subquery->addField('tn', 'nid');
    $subquery->distinct();
    $subquery->condition('tn.tid', $tids, 'IN');

    // Add the subquery as a join.
    $definition['left_table'] = $this->tableAlias;
    $definition['left_field'] = $this->realField;
    $definition['field'] = 'nid';
    $definition['type'] = 'INNER';
    $definition['adjusted'] = TRUE;
    $definition['table formula'] = $subquery;
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $definition);

    // There is no $base as we are joining to a query.
    $this->query->addRelationship('taxonomy_index_depth_join', $join, NULL, $this->relationship);
  }

}
