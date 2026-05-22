<?php

namespace Drupal\mongodb\modules\workspaces\EntityQuery;

use Drupal\mongodb\Driver\Database\mongodb\MongodbSQLException;
use Drupal\mongodb\EntityQuery\Query as BaseQuery;

/**
 * The MongoDB implementation of \Drupal\mongodb\EntityQuery\Query.
 */
class Query extends BaseQuery {

  use QueryTrait {
    prepare as traitPrepare;
  }

  /**
   * Stores the workspace revision field data.
   *
   * @var array
   *   An array with the revision field data.
   */
  protected $mongodbWorkspaceRevisionField;

  /**
   * Stores the workspace id field data.
   *
   * @var array
   *   An array with the id field data.
   */
  protected $mongodbWorkspaceIdField;

  /**
   * {@inheritdoc}
   */
  public function prepare() {
    $this->traitPrepare();

    // If the prepare() method from the trait decided that we need to alter this
    // query, we need to re-define the the key fields for fetchAllKeyed() as SQL
    // expressions.
    if ($this->mongodbSelect->getMetaData('active_workspace_id')) {
      // Since the query is against the base table, we have to take into account
      // that the revision ID might come from the workspace_association
      // relationship, and, as a consequence, the revision ID field is no longer
      // a simple SQL field but an expression.
      // $this->mongodbFields = [].

      $this->mongodbWorkspaceRevisionField = [
        'alias' => 'coalesce_' . $this->mongodbRevisionField,
        'field' => $this->mongodbRevisionField,
      ];
      $this->mongodbWorkspaceIdField = [
        'alias' => $this->mongodbIdField,
        'field' => $this->mongodbIdField,
      ];
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function finish() {
    if (!empty($this->mongodbWorkspaceRevisionField) && !empty($this->mongodbWorkspaceIdField)) {
      $this->mongodbSelect->addField('base_table', $this->mongodbWorkspaceRevisionField['field'], $this->mongodbWorkspaceRevisionField['alias']);
      $this->mongodbSelect->addField('base_table', $this->mongodbWorkspaceIdField['field'], $this->mongodbWorkspaceIdField['alias']);
    }
    return parent::finish();
  }

  /**
   * {@inheritdoc}
   */
  protected function result() {
    try {
      if (!$this->count && !$this->allRevisions) {
        if (empty($this->sort)) {
          // Return a keyed array of results. The key is either the revision_id
          // or the entity_id depending on whether the entity type supports
          // revisions. The value is always the entity id.
          $results = $this->mongodbSelect->execute()->fetchAll();

          $entities = [];
          foreach ($results as $record) {
            $revision_id = NULL;
            if (isset($record->{$this->mongodbRevisionField})) {
              $revision_id = $record->{$this->mongodbRevisionField};
            }
            elseif (isset($record->coalesce_target_entity_revision_id)) {
              $revision_id = $record->coalesce_target_entity_revision_id;
            }
            elseif (isset($record->{'coalesce_' . $this->mongodbRevisionField})) {
              $revision_id = $record->{'coalesce_' . $this->mongodbRevisionField};
            }

            if ($revision_id !== NULL && isset($record->{$this->mongodbIdField})) {
              $entities[$revision_id] = $record->{$this->mongodbIdField};
            }
          }
          return $entities;
        }
        else {
          $results = $this->mongodbSelect->execute()->fetchAll();
          foreach ($results as &$record) {
            if (!empty($record->{'coalesce_' . $this->mongodbRevisionField})) {
              $record->{$this->mongodbRevisionField} = $record->{'coalesce_' . $this->mongodbRevisionField};
              unset($record->{'coalesce_' . $this->mongodbRevisionField});
            }
          }
          $results = $this->sortQueryResult($results);
          if ($this->range) {
            // We also need the keys.
            $keys = array_slice(array_flip($results), $this->range['start'], $this->range['length']);
            $results = array_intersect_key($results, array_flip($keys));
          }
          return $results;
        }
      }
      return parent::result();
    }
    catch (MongodbSQLException) {
      return [];
    }
  }

}
