<?php

namespace Drupal\mongodb\Plugin\views\relationship;

use Drupal\taxonomy\Plugin\views\relationship\NodeTermData as CoreNodeTermData;

/**
 * Overrides the views relationship plugin "node_term_data".
 */
class NodeTermData extends CoreNodeTermData {

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    $this->ensureMyTable();

    $def = $this->definition;
    $def['table'] = 'taxonomy_term_data';

    $taxonomy_index = $this->query->addTable('taxonomy_index', $this->relationship);
    $def['left_table'] = $taxonomy_index;
    $def['left_field'] = 'tid';
    $def['field'] = 'tid';
    $def['type'] = empty($this->options['required']) ? 'LEFT' : 'INNER';
    if (array_filter($this->options['vids'])) {
      $def['extra'] = [['field' => 'taxonomy_term_current_revision.vid', 'value' => array_filter($this->options['vids']), 'operator' => 'IN']];
    }

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);

    // Use a short alias for this.
    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, 'taxonomy_term_data', $this->relationship);
  }

}
