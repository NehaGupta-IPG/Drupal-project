<?php

namespace Drupal\taxonomy\Plugin\views\filter;

use Drupal\taxonomy\TaxonomyIndexDepthJoinQueryTrait;
use Drupal\views\Attribute\ViewsFilter;

/**
 * Filter handler for taxonomy terms with depth using a term list join.
 *
 * This handler expands the term hierarchy in PHP using the term storage and
 * joins the taxonomy index on the resulting flat list of term IDs. This can
 * be more performant than the subquery used by the base handler on sites
 * with a fast entity cache.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("taxonomy_index_tid_depth_join")]
class TaxonomyIndexTidDepthJoin extends TaxonomyIndexTidDepth {

  use TaxonomyIndexDepthJoinQueryTrait;

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // If no filter values are present, then do nothing.
    if (count($this->value) == 0) {
      return;
    }

    // The normal use of ensureMyTable() here breaks Views.
    // So instead we trick the filter into using the alias of the base table.
    // See https://www.drupal.org/node/271833.
    // If a relationship is set, we must use the alias it provides.
    if (!empty($this->relationship)) {
      $this->tableAlias = $this->relationship;
    }
    // If no relationship, then use the alias of the base table.
    else {
      $this->tableAlias = $this->query->ensureTable($this->view->storage->get('base_table'));
    }

    $this->addDepthJoinToQuery($this->value);
  }

}
