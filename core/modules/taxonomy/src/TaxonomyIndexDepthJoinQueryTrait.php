<?php

namespace Drupal\taxonomy;

/**
 * Expands term IDs by depth in PHP and joins the result to the query.
 *
 * This is an alternative strategy to
 * \Drupal\taxonomy\TaxonomyIndexDepthQueryTrait. Instead of traversing the
 * term hierarchy in SQL with a union of joins on taxonomy_term__parent, the
 * hierarchy is traversed in PHP using the term storage. The resulting flat
 * list of term IDs is used in a single subquery on taxonomy_index which is
 * joined to the main views query using an INNER JOIN.
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

    if ($depth > 0) {
      // When the depth is positive search the children.
      foreach ($tids as $tid) {
        if ($term = $this->termStorage->load($tid)) {
          // For every term find all the children down to the configured
          // depth and add their IDs to the condition.
          foreach ($this->termStorage->loadTree($term->bundle(), $term->id(), $depth) as $item) {
            $tids[] = (int) $item->tid;
          }
        }
      }
    }
    elseif ($depth < 0) {
      // When the depth is negative search the parents. A term can have
      // multiple parents, so collect the parents of every parent at each
      // level.
      foreach ($tids as $tid) {
        if ($term = $this->termStorage->load($tid)) {
          $parents = [[$term]];
          foreach (range(1, -$depth) as $level) {
            $parents[$level] = [];
            foreach ($parents[$level - 1] as $parent) {
              $parents[$level] += $this->termStorage->loadParents($parent->id());
            }
            foreach ($parents[$level] as $parent) {
              $tids[] = (int) $parent->id();
            }
          }
        }
      }
    }

    // Build a subquery on the taxonomy index. DISTINCT ensures nodes tagged
    // with multiple terms in the hierarchy are not duplicated in the result.
    $connection = $this->query->getConnection();
    $subquery = $connection->select('taxonomy_index', 'tn');
    $subquery->addField('tn', 'nid');
    $subquery->distinct();
    $subquery->condition('tn.tid', array_unique($tids), 'IN');

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
