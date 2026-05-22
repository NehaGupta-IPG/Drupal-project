<?php

namespace Drupal\taxonomy;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\Sql\TableMappingInterface;

/**
 * Defines a Controller class for taxonomy terms.
 */
class TermStorage extends SqlContentEntityStorage implements TermStorageInterface {

  /**
   * Array of term parents keyed by vocabulary ID and child term ID.
   *
   * @var array
   */
  protected $treeParents = [];

  /**
   * Array of term ancestors keyed by vocabulary ID and parent term ID.
   *
   * @var array
   */
  protected $treeChildren = [];

  /**
   * Array of terms in a tree keyed by vocabulary ID and term ID.
   *
   * @var array
   */
  protected $treeTerms = [];

  /**
   * Array of loaded trees keyed by a cache id matching tree arguments.
   *
   * @var array
   */
  protected $trees = [];

  /**
   * Term ancestry keyed by ancestor term ID, keyed by term ID.
   *
   * @var \Drupal\taxonomy\TermInterface[][]
   */
  protected $ancestors;

  /**
   * The type of hierarchy allowed within a vocabulary.
   *
   * Possible values:
   * - VocabularyInterface::HIERARCHY_DISABLED: No parents.
   * - VocabularyInterface::HIERARCHY_SINGLE: Single parent.
   * - VocabularyInterface::HIERARCHY_MULTIPLE: Multiple parents.
   *
   * @var int[]
   *   An array of one the possible values above, keyed by vocabulary ID.
   */
  protected $vocabularyHierarchyType;

  /**
   * {@inheritdoc}
   *
   * @param array $values
   *   An array of values to set, keyed by property name. A value for the
   *   vocabulary ID ('vid') is required.
   */
  public function create(array $values = []) {
    // Save new terms with no parents by default.
    if (empty($values['parent'])) {
      $values['parent'] = [0];
    }
    $entity = parent::create($values);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function resetCache(?array $ids = NULL) {
    $this->ancestors = [];
    $this->treeChildren = [];
    $this->treeParents = [];
    $this->treeTerms = [];
    $this->trees = [];
    $this->vocabularyHierarchyType = [];
    parent::resetCache($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadParents($tid) {
    $terms = [];
    if ($tid && $term = $this->load($tid)) {
      foreach ($this->getParents($term) as $id => $parent) {
        // This method currently doesn't return the <root> parent.
        // @see https://www.drupal.org/node/2019905
        if (!empty($id)) {
          $terms[$id] = $parent;
        }
      }
    }

    return $terms;
  }

  /**
   * Returns a list of parents of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The parent taxonomy term entities keyed by term ID. If this term has a
   *   <root> parent, that item is keyed with 0 and will have NULL as value.
   *
   * @internal
   * @todo Refactor away when TreeInterface is introduced.
   */
  protected function getParents(TermInterface $term) {
    $parents = $ids = [];
    // Cannot use $this->get('parent')->referencedEntities() here because that
    // strips out the '0' reference.
    foreach ($term->get('parent') as $item) {
      if ($item->target_id == 0) {
        // The <root> parent.
        $parents[0] = NULL;
        continue;
      }
      $ids[] = $item->target_id;
    }

    // @todo Better way to do this? AND handle the NULL/0 parent?
    // Querying the terms again so that the same access checks are run when
    // getParents() is called as in Drupal version prior to 8.3.
    $loaded_parents = [];

    if ($ids) {
      $query = \Drupal::entityQuery('taxonomy_term')
        ->accessCheck(TRUE)
        ->condition('tid', $ids, 'IN');

      $loaded_parents = static::loadMultiple($query->execute());
    }

    return $parents + $loaded_parents;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllParents($tid) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    return (!empty($tid) && $term = $this->load($tid)) ? $this->getAncestors($term) : [];
  }

  /**
   * Returns all ancestors of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   A list of ancestor taxonomy term entities keyed by term ID.
   *
   * @internal
   * @todo Refactor away when TreeInterface is introduced.
   */
  protected function getAncestors(TermInterface $term) {
    if (!isset($this->ancestors[$term->id()])) {
      $this->ancestors[$term->id()] = [$term->id() => $term];
      $search[] = $term->id();

      while ($tid = array_shift($search)) {
        foreach ($this->getParents(static::load($tid)) as $id => $parent) {
          if ($parent && !isset($this->ancestors[$term->id()][$id])) {
            $this->ancestors[$term->id()][$id] = $parent;
            $search[] = $id;
          }
        }
      }
    }
    return $this->ancestors[$term->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function loadChildren($tid, $vid = NULL) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    return (!empty($tid) && $term = $this->load($tid)) ? $this->getChildren($term) : [];
  }

  /**
   * Returns all children terms of this term.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   A list of children taxonomy term entities keyed by term ID.
   *
   * @internal
   * @todo Refactor away when TreeInterface is introduced.
   */
  public function getChildren(TermInterface $term) {
    $query = \Drupal::entityQuery('taxonomy_term')
      ->accessCheck(TRUE)
      ->condition('parent', (int) $term->id());
    return static::loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function loadTree($vid, $parent = 0, $max_depth = NULL, $load_entities = FALSE) {
    $cache_key = implode(':', func_get_args());
    if (!isset($this->trees[$cache_key])) {
      // We cache trees, so it's not CPU-intensive to call on a term and its
      // children, too.
      if (!isset($this->treeChildren[$vid])) {
        $this->treeChildren[$vid] = [];
        $this->treeParents[$vid] = [];
        $this->treeTerms[$vid] = [];

        if ($this->database->driver() == 'mongodb') {
          $query = $this->database->select($this->getBaseTable(), 't')
            ->fields('t', ['tid', 'taxonomy_term_current_revision'])
            ->addTag('taxonomy_term_access')
            ->condition('taxonomy_term_current_revision.vid', $vid)
            ->condition('taxonomy_term_current_revision.default_langcode', TRUE)
            ->orderBy('taxonomy_term_current_revision.weight')
            ->orderBy('taxonomy_term_current_revision.name');

          $result = $query->execute()->fetchAll();
          foreach ($result as $row) {
            foreach ($row->taxonomy_term_current_revision as $current_revision) {
              $term = new \stdClass();
              $term->name = $current_revision['name'] ?? '';
              $term->depth = 0;
              $term->tid = $current_revision['tid'];
              $term->vid = $current_revision['vid'];
              $term->weight = $current_revision['weight'];

              if (is_array($current_revision['taxonomy_term_current_revision__parent'])) {
                foreach ($current_revision['taxonomy_term_current_revision__parent'] as $current_revision_parent) {
                  $term->parent = NULL;
                  if (isset($current_revision_parent['parent_target_id'])) {
                    $term->parent = $current_revision_parent['parent_target_id'];
                  }
                  if (!is_null($term->parent)) {
                    $this->treeChildren[$vid][$term->parent][] = $term->tid;
                    $this->treeParents[$vid][$term->tid][] = $term->parent;
                    $this->treeTerms[$vid][$term->tid] = $term;
                  }
                }
              }
            }
            unset($term->taxonomy_term_current_revision);
          }
        }
        else {
          $query = $this->database->select($this->getDataTable(), 't');
          $query->join('taxonomy_term__parent', 'p', $query->joinCondition()
            ->compare('t.tid', 'p.entity_id'));
          $query->addExpressionField('parent_target_id', 'parent');
          $result = $query
            ->addTag('taxonomy_term_access')
            ->fields('t')
            ->condition('t.vid', $vid)
            ->condition('t.default_langcode', 1)
            ->orderBy('t.weight')
            ->orderBy('t.name')
            ->execute();
          foreach ($result as $term) {
            $this->treeChildren[$vid][$term->parent][] = $term->tid;
            $this->treeParents[$vid][$term->tid][] = $term->parent;
            $this->treeTerms[$vid][$term->tid] = $term;
          }
        }
      }

      // Load full entities, if necessary. The entity controller statically
      // caches the results.
      $term_entities = [];
      if ($load_entities) {
        $term_entities = $this->loadMultiple(array_keys($this->treeTerms[$vid]));
      }

      $max_depth = (!isset($max_depth)) ? count($this->treeChildren[$vid]) : $max_depth;
      $tree = [];

      // Keeps track of the parents we have to process, the last entry is used
      // for the next processing step.
      $process_parents = [];
      $process_parents[] = $parent;

      // Loops over the parent terms and adds its children to the tree array.
      // Uses a loop instead of a recursion, because it's more efficient.
      while (count($process_parents)) {
        $parent = array_pop($process_parents);
        // The number of parents determines the current depth.
        $depth = count($process_parents);
        if ($max_depth > $depth && !empty($this->treeChildren[$vid][$parent])) {
          $has_children = FALSE;
          $child = current($this->treeChildren[$vid][$parent]);
          do {
            if (empty($child)) {
              break;
            }
            $term = $load_entities ? $term_entities[$child] : $this->treeTerms[$vid][$child];
            if (isset($this->treeParents[$vid][$load_entities ? $term->id() : $term->tid])) {
              // Clone the term so that the depth attribute remains correct
              // in the event of multiple parents.
              $term = clone $term;
            }
            $term->depth = $depth;
            if (!$load_entities) {
              unset($term->parent);
            }
            $tid = $load_entities ? $term->id() : $term->tid;
            $term->parents = $this->treeParents[$vid][$tid];
            $tree[] = $term;
            if (!empty($this->treeChildren[$vid][$tid])) {
              $has_children = TRUE;

              // We have to continue with this parent later.
              $process_parents[] = $parent;
              // Use the current term as parent for the next iteration.
              $process_parents[] = $tid;

              // Reset pointers for child lists because we step in there more
              // often with multi parents.
              reset($this->treeChildren[$vid][$tid]);
              // Move pointer so that we get the correct term the next time.
              next($this->treeChildren[$vid][$parent]);
              break;
            }
          } while ($child = next($this->treeChildren[$vid][$parent]));

          if (!$has_children) {
            // We processed all terms in this hierarchy-level, reset pointer
            // so that this function works the next time it gets called.
            reset($this->treeChildren[$vid][$parent]);
          }
        }
      }
      $this->trees[$cache_key] = $tree;
    }
    return $this->trees[$cache_key];
  }

  /**
   * {@inheritdoc}
   */
  public function nodeCount($vid) {
    if ($this->database->driver() == 'mongodb') {
      // @todo There is too little testing for this. Why is there a join in this
      // query.
      // @see \Drupal\Tests\taxonomy\Functional\TokenReplaceTest.
      $query = $this->database->select('taxonomy_index', 'ti');
      $query->addJoin('LEFT', 'taxonomy_term_data', 'td', $query->joinCondition()->compare('ti.tid', 'td.tid')->condition('taxonomy_term_current_revision.vid', $vid));
      $query->addTag('vocabulary_node_count');
      $results = $query->execute()->fetchAll();
      $nids = [];
      foreach ($results as $result) {
        if (isset($result->nid) && !in_array($result->nid, $nids)) {
          $nids[] = $result->nid;
        }
      }
      return count($nids);
    }
    else {
      $query = $this->database->select('taxonomy_index', 'ti');
      $query->addExpressionCountDistinct('ti.nid');
      $query->leftJoin($this->getBaseTable(), 'td', $query->joinCondition()->compare('ti.tid', 'td.tid'));
      $query->condition('td.vid', $vid);
      $query->addTag('vocabulary_node_count');
      return $query->execute()->fetchField();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetWeights($vid) {
    if ($this->database->driver() == 'mongodb') {
      $prefixed_table = $this->database->getPrefix() . 'taxonomy_term_data';
      $this->database->getConnection()->selectCollection($prefixed_table)->updateMany(
        [
          'vid' => $vid,
        ],
        [
          '$set' => [
            'weight' => 0,
            "taxonomy_term_current_revision.$[translation].weight" => 0,
          ],
        ],
        [
          'arrayFilters' => [
            ["translation.vid" => $vid],
          ],
          'session' => $this->database->getMongodbSession(),
        ],
      );
    }
    else {
      $this->database->update($this->getDataTable())
        ->fields(['weight' => 0])
        ->condition('vid', $vid)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNodeTerms(array $nids, array $vids = [], $langcode = NULL) {
    if ($this->database->driver() == 'mongodb') {
      $query = $this->database->select('taxonomy_term_data', 'td');
      foreach ($nids as &$nid) {
        $nid = (int) $nid;
      }
      $query->addJoin('INNER', 'taxonomy_index', 'tn', $query->joinCondition()->compare('tn.tid', 'td.tid'));
      $query->fields('td', ['tid']);
      $query->addField('tn', 'nid', 'node_nid');
      $query->condition('tn.nid', $nids, 'IN');
      $query->orderby('taxonomy_term_current_revision.weight');
      $query->orderby('taxonomy_term_current_revision.name');
      $query->addTag('taxonomy_term_access');
      if (!empty($vids)) {
        $query->condition('taxonomy_term_current_revision.vid', $vids, 'IN');
      }
      if (!empty($langcode)) {
        $query->condition('taxonomy_term_current_revision.langcode', $langcode);
      }

      $results = [];
      $all_tids = [];
      foreach ($query->execute() as $term_record) {
        if (isset($term_record->tid) && isset($term_record->node_nid)) {
          $results[$term_record->node_nid][] = $term_record->tid;
          $all_tids[] = $term_record->tid;
        }
      }
    }
    else {
      $query = $this->database->select($this->getDataTable(), 'td');
      $query->innerJoin('taxonomy_index', 'tn', $query->joinCondition()->compare('td.tid', 'tn.tid'));
      $query->fields('td', ['tid']);
      $query->addField('tn', 'nid', 'node_nid');
      $query->orderby('td.weight');
      $query->orderby('td.name');
      $query->condition('tn.nid', $nids, 'IN');
      $query->addTag('taxonomy_term_access');
      if (!empty($vids)) {
        $query->condition('td.vid', $vids, 'IN');
      }
      if (!empty($langcode)) {
        $query->condition('td.langcode', $langcode);
      }

      $results = [];
      $all_tids = [];
      foreach ($query->execute() as $term_record) {
        $results[$term_record->node_nid][] = $term_record->tid;
        $all_tids[] = $term_record->tid;
      }
    }

    $all_terms = $this->loadMultiple($all_tids);
    $terms = [];
    foreach ($results as $nid => $tids) {
      foreach ($tids as $tid) {
        $terms[$nid][$tid] = $all_terms[$tid];
      }
    }
    return $terms;
  }

  /**
   * {@inheritdoc}
   */
  public function getTermIdsWithPendingRevisions() {
    $table_mapping = $this->getTableMapping();
    $id_field = $table_mapping->getColumnNames($this->entityType->getKey('id'))['value'];
    $revision_field = $table_mapping->getColumnNames($this->entityType->getKey('revision'))['value'];
    $rta_field = $table_mapping->getColumnNames($this->entityType->getKey('revision_translation_affected'))['value'];
    $langcode_field = $table_mapping->getColumnNames($this->entityType->getKey('langcode'))['value'];
    $revision_default_field = $table_mapping->getColumnNames($this->entityType->getRevisionMetadataKey('revision_default'))['value'];

    if ($this->database->driver() == 'mongodb') {
      $latest_revision_table = $this->getJsonStorageLatestRevisionTable();

      $results = $this->database->select($this->getBaseTable(), 't')
        ->fields('t', [$id_field, $latest_revision_table])
        ->execute()
        ->fetchAll();

      $term_ids_with_pending_revisions = [];
      foreach ($results as $result) {
        $latest_revision = $result->{$latest_revision_table};
        $revision_id = NULL;
        foreach ($latest_revision as $latest_revision_language) {
          if (($latest_revision_language[$rta_field] === TRUE) && ($latest_revision_language[$revision_default_field] === FALSE)) {
            $revision_id = $latest_revision_language[$revision_field];
          }
        }
        if (!is_null($revision_id)) {
          $term_ids_with_pending_revisions[$result->{$id_field}] = $revision_id;
        }
      }

      return $term_ids_with_pending_revisions;
    }
    else {
      $query = $this->database->select($this->getRevisionDataTable(), 'tfr');
      $query->fields('tfr', [$id_field]);
      $query->addExpressionMax("tfr.$revision_field", $revision_field);

      $query->join($this->getRevisionTable(), 'tr',
        $query->joinCondition()
          ->compare("tfr.$revision_field", "tr.$revision_field")
          ->condition("tr.$revision_default_field", 0)
      );

      $inner_select = $this->database->select($this->getRevisionDataTable(), 't');
      $inner_select->condition("t.$rta_field", '1');
      $inner_select->fields('t', [$id_field, $langcode_field]);
      $inner_select->addExpressionMax("t.$revision_field", $revision_field);
      $inner_select
        ->groupBy("t.$id_field")
        ->groupBy("t.$langcode_field");

      $query->join($inner_select, 'mr',
        $query->joinCondition()
          ->compare("tfr.$revision_field", "mr.$revision_field")
          ->compare("tfr.$langcode_field", "mr.$langcode_field")
      );

      $query->groupBy("tfr.$id_field");

      return $query->execute()->fetchAllKeyed(1, 0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getVocabularyHierarchyType($vid) {
    // Return early if we already computed this value.
    if (isset($this->vocabularyHierarchyType[$vid])) {
      return $this->vocabularyHierarchyType[$vid];
    }

    $parent_field_storage = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId)['parent'];
    $table_mapping = $this->getTableMapping();

    $target_id_column = $table_mapping->getFieldColumnName($parent_field_storage, 'target_id');
    $delta_column = $table_mapping->getFieldColumnName($parent_field_storage, TableMappingInterface::DELTA);

    if ($this->database->driver() == 'mongodb') {
      $all_revisions_table = $table_mapping->getJsonStorageAllRevisionsTable(0);
      $parent_table = $table_mapping->getJsonStorageDedicatedTableName($parent_field_storage, $all_revisions_table);

      $rows = $this->database->select($this->getBaseTable())
        ->fields($this->getBaseTable(), [$all_revisions_table])
        ->condition("$all_revisions_table.vid", $vid)
        ->execute()
        ->fetchAll();

      $max_parent_id = 0;
      $max_delta = 0;
      foreach ($rows as $row) {
        if (isset($row->{$all_revisions_table})) {
          foreach ($row->{$all_revisions_table} as $taxonomy_term_revision) {
            if (isset($taxonomy_term_revision[$parent_table])) {
              foreach ($taxonomy_term_revision[$parent_table] as $parent_table_row) {
                $parent_id = (int) $parent_table_row['parent_target_id'];
                if ($parent_id > $max_parent_id) {
                  $max_parent_id = (int) $parent_id;
                }
                $delta = (int) $parent_table_row['delta'];
                if ($delta > $max_delta) {
                  $max_delta = (int) $delta;
                }
              }
            }
          }
        }
      }

      // Create the result as it is created for a relational database.
      $result = [
        0 => (object) [
          'max_parent_id' => $max_parent_id,
          'max_delta' => $max_delta,
        ],
      ];
    }
    else {
      $query = $this->database->select($table_mapping->getFieldTableName('parent'), 'p');
      $query->addExpressionMax("$target_id_column", 'max_parent_id');
      $query->addExpressionMax("$delta_column", 'max_delta');
      $query->condition('bundle', $vid);

      $result = $query->execute()->fetchAll();
    }

    // If all the terms have the same parent, the parent can only be root (0).
    if ((int) $result[0]->max_parent_id === 0) {
      $this->vocabularyHierarchyType[$vid] = VocabularyInterface::HIERARCHY_DISABLED;
    }
    // If no term has a delta higher than 0, no term has multiple parents.
    elseif ((int) $result[0]->max_delta === 0) {
      $this->vocabularyHierarchyType[$vid] = VocabularyInterface::HIERARCHY_SINGLE;
    }
    else {
      $this->vocabularyHierarchyType[$vid] = VocabularyInterface::HIERARCHY_MULTIPLE;
    }

    return $this->vocabularyHierarchyType[$vid];
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    /** @var string[] $vars */
    $vars = parent::__sleep();
    // Do not serialize static cache.
    unset($vars['ancestors'], $vars['treeChildren'], $vars['treeParents'], $vars['treeTerms'], $vars['trees'], $vars['vocabularyHierarchyType']);
    return $vars;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup(): void {
    parent::__wakeup();
    // Initialize static caches.
    $this->ancestors = [];
    $this->treeChildren = [];
    $this->treeParents = [];
    $this->treeTerms = [];
    $this->trees = [];
    $this->vocabularyHierarchyType = [];
  }

}
