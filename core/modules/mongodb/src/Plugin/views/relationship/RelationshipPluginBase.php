<?php

namespace Drupal\mongodb\Plugin\views\relationship;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase as CoreRelationshipPluginBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Overrides the views relationship base class.
 */
abstract class RelationshipPluginBase extends CoreRelationshipPluginBase {

  /**
   * The actual field in the database table.
   *
   * Maybe different on other kind of query plugins/special handlers.
   *
   * @var string
   */
  public $revisionableRealField;

  /**
   * With field you can override the realField if the real field is not set.
   *
   * @var string
   */
  public $revisionableField;

  /**
   * Overrides \Drupal\views\Plugin\views\HandlerBase::init().
   *
   * Init handler to let relationships live on tables other than
   * the table they operate on.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if (isset($this->definition['relationship table'])) {
      $this->table = $this->definition['relationship table'];
    }
    if (isset($this->definition['relationship field'])) {
      // Set both realField and field so custom handler can rely on the old
      // field value.
      $this->realField = $this->field = $this->definition['relationship field'];
    }

    if (isset($this->definition['revisionable relationship field'])) {
      // Set both realField and field so custom handler can rely on the old
      // field value.
      $this->revisionableRealField = $this->revisionableField = $this->definition['revisionable relationship field'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!empty($this->definition['deprecated'])) {
      @trigger_error($this->definition['deprecated'], E_USER_DEPRECATED);
    }

    // Figure out what base table this relationship brings to the party.
    $table_data = Views::viewsData()->get($this->definition['base']);
    $base_field = empty($this->definition['base field']) ? $table_data['table']['base']['field'] : $this->definition['base field'];

    $this->ensureMyTable();

    $def = $this->definition;
    $def['table'] = $this->definition['base'];
    $def['field'] = $base_field;
    $def['left_table'] = $this->tableAlias;
    $def['left_field'] = $this->realField;
    $def['adjusted'] = TRUE;
    if (!empty($this->options['required'])) {
      $def['type'] = 'INNER';
    }

    if (!empty($this->definition['extra'])) {
      $def['extra'] = $this->definition['extra'];
    }

    if (!empty($def['join_id'])) {
      $id = $def['join_id'];
    }
    else {
      $id = 'standard';
    }

    // Extra stuff for MongoDB.
    if (!empty($this->options['revisionable_join'])) {
      $def['revisionable_join'] = (bool) $this->options['revisionable_join'];

      $all_revisions_table = NULL;
      $latest_revision_table = NULL;
      if ($this->query->hasLatestRevisionFilter() && !empty($this->definition['revisionable entity type'])) {
        $entity_storage = \Drupal::entityTypeManager()->getStorage($this->definition['revisionable entity type']);
        $all_revisions_table = $entity_storage->getAllRevisionsTable();
        $latest_revision_table = $entity_storage->getAllRevisionsTable();
      }

      if (!empty($this->definition['revisionable base field'])) {
        $def['field'] = $this->definition['revisionable base field'];
        if ($all_revisions_table && $latest_revision_table) {
          $def['field'] = str_replace($all_revisions_table, $latest_revision_table, $def['field']);
        }
      }

      if (!empty($this->definition['revisionable relationship field'])) {
        $def['left_field'] = $this->definition['revisionable relationship field'];
      }

      if (!empty($this->definition['revisionable extra'])) {
        $def['extra'] = $this->definition['revisionable extra'];
        if ($all_revisions_table && $latest_revision_table) {
          foreach ($def['extra'] as &$extra) {
            if (isset($extra['field'])) {
              $extra['field'] = str_replace($all_revisions_table, $latest_revision_table, $extra['field']);
            }
          }
        }
      }
    }

    $join = Views::pluginManager('join')->createInstance($id, $def);

    // Use a short alias for this.
    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, $this->definition['base'], $this->relationship);

    // Add access tags if the base table provide it.
    if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
      $access_tag = $table_data['table']['base']['access query tag'];
      $this->query->addTag($access_tag);
    }
  }

}
