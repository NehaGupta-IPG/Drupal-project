<?php

declare(strict_types=1);

namespace Drupal\workspaces\Hook;

use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\views\Plugin\views\join\JoinPluginInterface;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\ViewExecutable;
use Drupal\views\ViewsData;
use Drupal\workspaces\WorkspaceTracker;
use Drupal\workspaces\WorkspaceInformationInterface;
use Drupal\workspaces\WorkspaceManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Defines a class for altering views queries.
 */
class ViewsOperations {

  /**
   * An array of tables adjusted for workspace_association join.
   *
   * @var \WeakMap
   */
  private \WeakMap $adjustedTables;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected WorkspaceManagerInterface $workspaceManager,
    protected WorkspaceInformationInterface $workspaceInfo,
    protected LanguageManagerInterface $languageManager,
    protected ?ViewsData $viewsData = NULL,
    #[Autowire(service: 'plugin.manager.views.join')]
    protected ?ViewsHandlerManager $viewsJoinPluginManager = NULL,
  ) {
    $this->adjustedTables = new \WeakMap();
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    // Don't alter any views queries if we're not in a workspace context.
    if (!$this->workspaceManager->hasActiveWorkspace()) {
      return;
    }

    // Don't alter any non-sql views queries.
    if (!$query instanceof Sql) {
      return;
    }

    // Find out what entity types are represented in this query.
    $entity_type_ids = [];
    foreach ($query->relationships as $info) {
      $table_data = $this->viewsData->get($info['base']);
      if (empty($table_data['table']['entity type'])) {
        continue;
      }
      $entity_type_id = $table_data['table']['entity type'];
      // This construct ensures each entity type exists only once.
      $entity_type_ids[$entity_type_id] = $entity_type_id;
    }

    $entity_type_definitions = $this->entityTypeManager->getDefinitions();
    foreach ($entity_type_ids as $entity_type_id) {
      if ($this->workspaceInfo->isEntityTypeSupported($entity_type_definitions[$entity_type_id])) {
        $this->alterQueryForEntityType($query, $entity_type_definitions[$entity_type_id]);
      }
    }
  }

  /**
   * Alters the entity type tables for a Views query.
   *
   * This should only be called after determining that this entity type is
   * involved in the query, and that a non-default workspace is in use.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   */
  protected function alterQueryForEntityType(Sql $query, EntityTypeInterface $entity_type): void {
    /** @var \Drupal\Core\Entity\Sql\DefaultTableMapping $table_mapping */
    $table_mapping = $this->entityTypeManager->getStorage($entity_type->id())->getTableMapping();
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type->id());
    $dedicated_field_storage_definitions = array_filter($field_storage_definitions, function ($definition) use ($table_mapping) {
      return $table_mapping->requiresDedicatedTableStorage($definition);
    });
    $dedicated_field_data_tables = array_map(function ($definition) use ($table_mapping) {
      return $table_mapping->getDedicatedDataTableName($definition);
    }, $dedicated_field_storage_definitions);

    $move_workspace_tables = [];
    $table_queue =& $query->getTableQueue();
    foreach ($table_queue as $alias => &$table_info) {
      // If we reach the workspace_association array item before any candidates,
      // then we do not need to move it.
      if ($table_info['table'] == 'workspace_association') {
        break;
      }

      // Any dedicated field table is a candidate.
      if ($field_name = array_search($table_info['table'], $dedicated_field_data_tables, TRUE)) {
        $relationship = $table_info['relationship'];

        // There can be reverse relationships used. If so, Workspaces can't do
        // anything with them. Detect this and skip.
        if ($table_info['join']->field != 'entity_id') {
          continue;
        }

        // Get the dedicated revision table name.
        $new_table_name = $table_mapping->getDedicatedRevisionTableName($field_storage_definitions[$field_name]);

        // Now add the workspace_association table.
        $workspace_association_table = $this->ensureWorkspaceAssociationTable($entity_type->id(), $query, $relationship);

        // Update the join to use our COALESCE.
        $revision_field = $entity_type->getKey('revision');
        $table_info['join']->leftFormula = "COALESCE($workspace_association_table.target_entity_revision_id, $relationship.$revision_field)";

        // Update the join and the table info to our new table name, and to join
        // on the revision key.
        $table_info['table'] = $new_table_name;
        $table_info['join']->table = $new_table_name;
        $table_info['join']->field = 'revision_id';

        // Finally, if we added the workspace_association table we have to move
        // it in the table queue so that it comes before this field.
        if (empty($move_workspace_tables[$workspace_association_table])) {
          $move_workspace_tables[$workspace_association_table] = $alias;
        }
      }
    }
    unset($table_info);

    // JOINs must be in order. i.e, any tables you mention in the ON clause of a
    // JOIN must appear prior to that JOIN. Since we're modifying a JOIN in
    // place, and adding a new table, we must ensure that the new table appears
    // prior to this one. So we recorded at what index we saw that table, and
    // then use array_splice() to move the workspace_association table join to
    // the correct position.
    foreach ($move_workspace_tables as $workspace_association_table => $alias) {
      $this->moveEntityTable($query, $workspace_association_table, $alias);
    }

    $base_entity_table = $entity_type->isTranslatable() ? $entity_type->getDataTable() : $entity_type->getBaseTable();

    $base_fields = array_diff($table_mapping->getFieldNames($entity_type->getBaseTable()), [$entity_type->getKey('langcode')]);
    $revisionable_fields = array_diff($table_mapping->getFieldNames($entity_type->getRevisionDataTable()), $base_fields);

    // Build a list of all relationships that might be for our table.
    $relationships = [];
    foreach ($query->relationships as $relationship => $info) {
      if ($info['base'] == $base_entity_table) {
        $relationships[] = $relationship;
      }
    }

    // A relationship can join on a revisionable base field, e.g. the 'Content:
    // Author' relationship joins 'users_field_data' on 'node_field_data.uid'.
    // Collect those joins first, because rewriting them adds tables to the
    // table queue and moves them around.
    $joins_to_rewrite = [];
    foreach ($table_queue as $alias => $join_table_info) {
      $join = $join_table_info['join'] ?? NULL;
      // Skip the joins added by this class, they already point to the revision
      // table, as well as joins that use a formula for the left side.
      if (!$join instanceof JoinPluginInterface || $this->adjustedTables->offsetExists($join) || $join->leftFormula) {
        continue;
      }

      if (in_array($join->leftTable, $relationships, TRUE) && in_array($join->leftField, $revisionable_fields, TRUE)) {
        $joins_to_rewrite[$alias] = $join;
      }
    }

    foreach ($joins_to_rewrite as $alias => $join) {
      $revision_table = $this->ensureRevisionTable($entity_type, $query, $join->leftTable);
      if (!$revision_table) {
        continue;
      }

      $join->leftFormula = "$revision_table.$join->leftField";

      // The revision table has to come before the join that references it, and
      // the 'workspace_association' table before the revision table. Move the
      // revision table first, otherwise moving it would leave the
      // 'workspace_association' table behind.
      $this->moveEntityTable($query, $revision_table, $alias);
      $workspace_association_table = $this->ensureWorkspaceAssociationTable($entity_type->id(), $query, $join->leftTable);
      $this->moveEntityTable($query, $workspace_association_table, $revision_table);
    }

    // Go through and look to see if we have to modify fields.
    foreach ($query->fields as &$field_info) {
      // Fields without a table are formulas, e.g. a date sort with a
      // granularity coarser than 'second'. We can't swap the table, so rewrite
      // any revisionable field reference inside the formula instead.
      if (empty($field_info['table'])) {
        if (is_string($field_info['field'])) {
          $field_info['field'] = $this->rewriteRevisionableFieldReferences($field_info['field'], $query, $entity_type, $relationships, $revisionable_fields);
        }
        continue;
      }

      // Dereference the alias into the actual table.
      $table = $table_queue[$field_info['table']]['table'];
      if ($table == $base_entity_table && in_array($field_info['field'], $revisionable_fields)) {
        $relationship = $table_queue[$field_info['table']]['alias'];
        $alias = $this->ensureRevisionTable($entity_type, $query, $relationship);
        if ($alias) {
          // Change the base table to use the revision table instead.
          $field_info['table'] = $alias;
        }
      }
    }
    unset($field_info);

    // Rewrite revisionable field references in the WHERE and HAVING clauses. A
    // reference can be a bare column, a column wrapped in an aggregate like
    // SUM(base.field), or one inside a condition object built by a filter.
    foreach (['where', 'having'] as $clause_type) {
      foreach ($query->{$clause_type} as &$clauses) {
        foreach ($clauses['conditions'] as &$condition_info) {
          $this->rewriteConditionField($condition_info['field'], $query, $entity_type, $relationships, $revisionable_fields);
        }
        unset($condition_info);
      }
      unset($clauses);
    }

    // GROUP BY clauses built from the selected fields already follow the
    // rewrites above, but clauses added directly to the query can still target
    // the base table.
    foreach ($query->groupby as &$group_field) {
      if (is_string($group_field)) {
        $group_field = $this->rewriteRevisionableFieldReferences($group_field, $query, $entity_type, $relationships, $revisionable_fields);
      }
    }
    unset($group_field);

    // ORDER BY usually references a field alias, which follows the rewrites
    // above. A click sort references the column directly (e.g.
    // node_field_data.changed), so rewrite it too, or it won't match the
    // SELECT list under DISTINCT.
    foreach ($query->orderby as &$order) {
      if (is_string($order['field'])) {
        $order['field'] = $this->rewriteRevisionableFieldReferences($order['field'], $query, $entity_type, $relationships, $revisionable_fields);
      }
    }
    unset($order);
  }

  /**
   * Rewrites references to revisionable base fields in a query expression.
   *
   * Replaces every '<relationship>.<field>' reference to a revisionable field
   * with a reference to the entity type's revision table, adding that table to
   * the query if it's not there yet. This is a plain string replacement, not
   * SQL parsing, so a reference inside a quoted string or a comment in a raw
   * snippet is rewritten too.
   *
   * @param string $expression
   *   The query expression, e.g. a field, a formula or a condition snippet.
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string[] $relationships
   *   The relationships that point to the entity type's base table.
   * @param string[] $revisionable_fields
   *   The names of the revisionable fields of the entity type.
   *
   * @return string
   *   The rewritten expression.
   */
  protected function rewriteRevisionableFieldReferences(string $expression, Sql $query, EntityTypeInterface $entity_type, array $relationships, array $revisionable_fields): string {
    foreach ($relationships as $relationship) {
      foreach ($revisionable_fields as $field) {
        $reference = "$relationship.$field";
        // Skip the regex entirely for the common case of no match at all.
        if (!str_contains($expression, $reference)) {
          continue;
        }

        // Add the revision table only when the pattern really matches, and use
        // a callback so that the replacement is not scanned for backreferences.
        $pattern = '/\b' . preg_quote($reference, '/') . '\b/';
        $expression = preg_replace_callback($pattern, function () use ($query, $entity_type, $relationship, $field) {
          // Reference the revision table instead of the base table.
          $alias = $this->ensureRevisionTable($entity_type, $query, $relationship);
          return $alias ? "$alias.$field" : "$relationship.$field";
        }, $expression);
      }
    }

    return $expression;
  }

  /**
   * Rewrites revisionable field references in a WHERE or HAVING condition.
   *
   * @param mixed $field
   *   The condition field, rewritten in place.
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param string[] $relationships
   *   The relationships that point to the entity type's base table.
   * @param string[] $revisionable_fields
   *   The names of the revisionable fields of the entity type.
   */
  protected function rewriteConditionField(mixed &$field, Sql $query, EntityTypeInterface $entity_type, array $relationships, array $revisionable_fields): void {
    if ($field instanceof ConditionInterface) {
      // Changing the array in place doesn't mark the condition as changed, so
      // this relies on Views compiling conditions after the alter runs.
      $conditions = &$field->conditions();
      foreach ($conditions as &$condition_info) {
        // Skip the '#conjunction' element and anything without a field.
        if (is_array($condition_info) && isset($condition_info['field'])) {
          $this->rewriteConditionField($condition_info['field'], $query, $entity_type, $relationships, $revisionable_fields);
        }
      }
      unset($condition_info);
    }
    elseif (is_string($field)) {
      $field = $this->rewriteRevisionableFieldReferences($field, $query, $entity_type, $relationships, $revisionable_fields);
    }
  }

  /**
   * Adds the 'workspace_association' table to a views query.
   *
   * @param string $entity_type_id
   *   The ID of the entity type to join.
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $relationship
   *   The primary table alias this table is related to.
   *
   * @return string
   *   The alias of the 'workspace_association' table.
   */
  protected function ensureWorkspaceAssociationTable(string $entity_type_id, Sql $query, string $relationship): string {
    if (isset($query->tables[$relationship]['workspace_association'])) {
      return $query->tables[$relationship]['workspace_association']['alias'];
    }

    $table_data = $this->viewsData->get($query->relationships[$relationship]['base']);

    // Construct the join.
    $definition = [
      'table' => 'workspace_association',
      'field' => WorkspaceTracker::getIdField($entity_type_id),
      'left_table' => $relationship,
      'left_field' => $table_data['table']['base']['field'],
      'extra' => [
        [
          'field' => 'target_entity_type_id',
          'value' => $entity_type_id,
        ],
        [
          'field' => 'workspace',
          'value' => $this->workspaceManager->getActiveWorkspace()->id(),
        ],
      ],
      'type' => 'LEFT',
    ];

    $join = $this->viewsJoinPluginManager->createInstance('standard', $definition);
    $join->adjusted = TRUE;

    return $query->queueTable('workspace_association', $relationship, $join);
  }

  /**
   * Adds the revision table of an entity type to a query object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The query plugin object for the query.
   * @param string $relationship
   *   The name of the relationship.
   *
   * @return string
   *   The alias of the relationship.
   */
  protected function ensureRevisionTable(EntityTypeInterface $entity_type, Sql $query, string $relationship): string {
    // Get the alias for the 'workspace_association' table we chain off of in
    // the COALESCE.
    $workspace_association_table = $this->ensureWorkspaceAssociationTable($entity_type->id(), $query, $relationship);

    // Get the name of the revision table and revision key.
    $base_revision_table = $entity_type->isTranslatable() ? $entity_type->getRevisionDataTable() : $entity_type->getRevisionTable();
    $revision_field = $entity_type->getKey('revision');

    // If the table was already added and has a join against the same field on
    // the revision table, reuse that rather than adding a new join.
    if (isset($query->tables[$relationship][$base_revision_table])) {
      $table_queue =& $query->getTableQueue();
      $alias = $query->tables[$relationship][$base_revision_table]['alias'];
      if (isset($table_queue[$alias]['join']->field) && $table_queue[$alias]['join']->field == $revision_field) {
        // If this table previously existed, but was not added by us, we need
        // to modify the join and make sure that 'workspace_association' comes
        // first.
        if (!$this->adjustedTables->offsetExists($table_queue[$alias]['join'])) {
          $table_queue[$alias]['join'] = $this->getRevisionTableJoin($relationship, $base_revision_table, $revision_field, $workspace_association_table, $entity_type);
          // We also have to ensure that our 'workspace_association' comes
          // before this.
          $this->moveEntityTable($query, $workspace_association_table, $alias);
        }

        return $alias;
      }
    }

    // Construct a new join.
    $join = $this->getRevisionTableJoin($relationship, $base_revision_table, $revision_field, $workspace_association_table, $entity_type);
    return $query->queueTable($base_revision_table, $relationship, $join);
  }

  /**
   * Fetches a join for a revision table using the workspace_association table.
   *
   * @param string $relationship
   *   The relationship to use in the view.
   * @param string $table
   *   The table name.
   * @param string $field
   *   The field to join on.
   * @param string $workspace_association_table
   *   The alias of the 'workspace_association' table joined to the main entity
   *   table.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type that is being queried.
   *
   * @return \Drupal\views\Plugin\views\join\JoinPluginInterface
   *   An adjusted views join object to add to the query.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getRevisionTableJoin(string $relationship, string $table, string $field, string $workspace_association_table, EntityTypeInterface $entity_type): JoinPluginInterface {
    $definition = [
      'table' => $table,
      'field' => $field,
      'left_table' => $relationship,
      'left_formula' => "COALESCE($workspace_association_table.target_entity_revision_id, $relationship.$field)",
    ];

    if ($entity_type->isTranslatable() && $this->languageManager->isMultilingual()) {
      $langcode_field = $entity_type->getKey('langcode');
      $definition['extra'] = [
        ['field' => $langcode_field, 'left_field' => $langcode_field],
      ];
    }

    /** @var \Drupal\views\Plugin\views\join\JoinPluginInterface $join */
    $join = $this->viewsJoinPluginManager->createInstance('standard', $definition);
    $join->adjusted = TRUE;
    $this->adjustedTables[$join] = TRUE;

    return $join;
  }

  /**
   * Moves a table to appear before the given alias.
   *
   * Because Workspaces chains possibly pre-existing tables onto the
   * 'workspace_association' table and the revision table, we have to ensure
   * that those tables appear in the query before the alias they're chained on
   * or the SQL is invalid.
   *
   * @param \Drupal\views\Plugin\views\query\Sql $query
   *   The SQL query object.
   * @param string $table_alias
   *   The alias of the table to move.
   * @param string $alias
   *   The alias of the table it needs to appear before.
   */
  protected function moveEntityTable(Sql $query, string $table_alias, string $alias): void {
    $table_queue =& $query->getTableQueue();
    $keys = array_keys($table_queue);
    $current_index = array_search($table_alias, $keys);
    $index = array_search($alias, $keys);

    // If it's already before our table, we don't need to move it, as we could
    // accidentally move it forward.
    if ($current_index < $index) {
      return;
    }
    $splice = [$table_alias => $table_queue[$table_alias]];
    unset($table_queue[$table_alias]);

    // Now move the item to the proper location in the array. Don't use
    // array_splice() because that breaks indices.
    $table_queue = array_slice($table_queue, 0, $index, TRUE) +
      $splice +
      array_slice($table_queue, $index, NULL, TRUE);
  }

}
