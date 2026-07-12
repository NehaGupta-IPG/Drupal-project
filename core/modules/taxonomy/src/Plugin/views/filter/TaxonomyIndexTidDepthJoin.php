<?php

namespace Drupal\taxonomy\Plugin\views\filter;

use Drupal\views\Views;

/**
 * Filter handler for taxonomy terms with depth using joins.
 *
 * This handler can be more performant than the normal one due to the fact that
 * it uses joins instead of subqueries.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("taxonomy_index_tid_depth_join")
 */
class TaxonomyIndexTidDepthJoin extends TaxonomyIndexTidDepth {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // If no filter values are present, then do nothing.
    if (count($this->value) == 0) {
      return;
    }
    elseif (count($this->value) == 1) {
      // Sometimes $this->value is an array with a single element so convert it.
      if (is_array($this->value)) {
        $this->value = current($this->value);
      }
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

    // The tids variable can be an integer or an array of integers.
    $tids = is_array($this->value) ? $this->value : [$this->value];

    if ($this->options['depth'] > 0) {
      // When the depth is positive search the children.
      foreach ($tids as $tid) {
        // The term must be loaded to get vid for use in taxonomy_get_tree().
        if ($term = $this->termStorage->load($tid)) {
          // For every tid argument find all the children down to the depth set
          // in the options and save the tids for the condition.
          $tree = $this->termStorage->loadTree($term->getVocabularyId(), $term->id(), (int) $this->options['depth']);
          $tids = array_merge($tids, array_map(function ($term) {
            return $term->id();
          }, $tree));
        }
      }
    }
    elseif ($this->options['depth'] < 0) {
      // When the depth is negative search the parents.
      foreach ($tids as $tid) {
        // For every tid argument find all the parents up to the depth set
        // in the options and add the tids into the array. Since there is
        // no taxonomy function to get all parents with a depth limit it
        // is done here building a multidimensional array.
        if ($term = $this->termStorage->load($tid)) {
          // A variable is needed to track the current depth level.
          $n = 0;
          // Initialize our depth based parents array with the leaf term.
          $parents[$n--][] = $term;
          while ($n >= $this->options['depth']) {
            // At each depth find the parents of the current terms.
            // It is important to note that it is possible for a term to have
            // multiple parents so get the parents of every parent and so on.
            $parents[$n] = [];
            foreach ($parents[$n + 1] as $term) {
              $parents[$n] += $this->termStorage->loadParents($term->id());
            }
            // Save all the tids for the condition.
            $tids = array_merge($tids, array_map(function ($term) {
              return $term->id();
            }, $parents[$n]));
            $n--;
          }
        }
      }
    }

    // Join on taxonomy index table.
    $configuration = [
      'type' => 'INNER',
      'table' => 'taxonomy_index',
      'field' => 'nid',
      'left_table' => $this->tableAlias,
      'left_field' => $this->realField,
      'operator' => '=',
      'extra' => [
        0 => [
          'field' => 'tid',
          'value' => $tids,
        ],
      ],
    ];
    $join = Views::pluginManager('join')->createInstance('standard', $configuration);

    $this->query->addRelationship('taxonomy_index', $join, 'node');
  }

}
