<?php

namespace Drupal\node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a storage handler class that handles the node grants system.
 *
 * This is used to build node query access.
 *
 * @ingroup node_access
 */
class NodeGrantDatabaseStorage implements NodeGrantDatabaseStorageInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a NodeGrantDatabaseStorage object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(Connection $database, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager) {
    $this->database = $database;
    $this->moduleHandler = $module_handler;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function access(NodeInterface $node, $operation, AccountInterface $account) {
    // Grants only support these operations.
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      return AccessResult::neutral();
    }

    // If no module implements the hook or the node does not have an id there is
    // no point in querying the database for access grants.
    if (!$this->moduleHandler->hasImplementations('node_grants') || $node->isNew()) {
      // Return the equivalent of the default grant, defined by
      // self::writeDefault().
      if ($operation === 'view') {
        $result = AccessResult::allowedIf($node->isPublished());
        if (!$node->isNew()) {
          $result->addCacheableDependency($node);
        }

        return $result;
      }

      return AccessResult::neutral();
    }

    // Check the database for potential access grants.
    $query = $this->database->select('node_access');
    if ($this->database->driver() == 'mongodb') {
      $query->fields('node_access', ['nid', 'langcode', 'gid', 'realm']);
      // Only interested for granting in the current operation.
      $query->condition('grant_' . $operation, TRUE);
    }
    else {
      $query->addExpressionConstant('1');
      // Only interested for granting in the current operation.
      $query->condition('grant_' . $operation, TRUE, '>=');
    }
    // Check for grants for this node and the correct langcode. New translations
    // do not yet have a langcode and must check the fallback node record.
    $nids = $query->andConditionGroup()
      ->condition('nid', (int) $node->id());
    if (!$node->isNewTranslation()) {
      $nids->condition('langcode', $node->language()->getId());
    }
    else {
      $nids->condition('fallback', TRUE);
    }
    // If the node is published, also take the default grant into account. The
    // default is saved with a node ID of 0.
    $status = $node->isPublished();
    if ($status) {
      $nids = $query->orConditionGroup()
        ->condition($nids)
        ->condition('nid', 0);
    }
    $query->condition($nids);
    $query->range(0, 1);

    $grants = $this->buildGrantsQueryCondition(node_access_grants($operation, $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }

    // Only the 'view' node grant can currently be cached; the others currently
    // don't have any cacheability metadata. Hopefully, we can add that in the
    // future, which would allow this access check result to be cacheable in all
    // cases. For now, this must remain marked as uncacheable, even when it is
    // theoretically cacheable, because we don't have the necessary metadata to
    // know it for a fact.
    $set_cacheability = function (AccessResult $access_result) use ($operation) {
      $access_result->addCacheContexts(['user.node_grants:' . $operation]);
      if ($operation !== 'view') {
        $access_result->setCacheMaxAge(0);
      }
      return $access_result;
    };

    if ($this->database->driver() == 'mongodb') {
      $count = $query->execute()->fetchAll();
      $query_result = count($count);
    }
    else {
      $query_result = $query->execute()->fetchField();
    }

    if ($query_result) {
      return $set_cacheability(AccessResult::allowed());
    }
    else {
      return $set_cacheability(AccessResult::neutral());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkAll(AccountInterface $account) {
    $query = $this->database->select('node_access');
    if ($this->database->driver() == 'mongodb') {
      $query->fields('node_access', ['nid', 'langcode', 'gid', 'realm']);
      $query
        ->condition('nid', 0)
        ->condition('grant_view', TRUE);
    }
    else {
      $query->addExpressionCountAll();
      $query
        ->condition('nid', 0)
        ->condition('grant_view', TRUE, '>=');
    }

    $grants = $this->buildGrantsQueryCondition(node_access_grants('view', $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }

    if ($this->database->driver() == 'mongodb') {
      $count = $query->execute()->fetchAll();
      return count($count);
    }
    else {
      return $query->execute()->fetchField();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery($query, array $tables, $operation, AccountInterface $account, $base_table) {
    if (!$langcode = $query->getMetaData('langcode')) {
      $langcode = FALSE;
    }

    // Find all instances of the base table being joined which could appear
    // more than once in the query, and could be aliased. Join each one to
    // the node_access table.
    $grants = node_access_grants($operation, $account);
    // If any grant exists for the specified user, then user has access to the
    // node for the specified operation.
    $grant_conditions = $this->buildGrantsQueryCondition($grants);
    $grants_exist = count($grant_conditions->conditions()) > 0;

    $is_multilingual = \Drupal::languageManager()->isMultilingual();
    foreach ($tables as $table_alias => $tableinfo) {
      $table = $tableinfo['table'];
      if (!($table instanceof SelectInterface) && $table == $base_table) {
        if ($this->database->driver() == 'mongodb') {
          // Attach conditions to the sub-query for nodes.
          if ($grants_exist) {
            $query->condition($grant_conditions);
          }

          $query->condition('grant_' . $operation, TRUE);

          if ($is_multilingual) {
            // If no specific langcode to check for is given, use the grant entry
            // which is set as a fallback.
            // If a specific langcode is given, use the grant entry for it.
            if ($langcode === FALSE) {
              $query->condition('fallback', TRUE);
            }
            else {
              $query->condition('langcode', $langcode);
            }
          }

          $query->addJoin('INNER', 'node_access', 'na', $query->joinCondition()->compare('na.nid', "$base_table.nid"));
          $query->unwindJoinAndAddFields('na', ['nid', 'langcode', 'fallback', 'gid', 'realm', 'grant_' . $operation]);
        }
        else {
          // Set the subquery.
          $subquery = $this->database->select('node_access', 'na')
            ->fields('na', ['nid']);

          // Attach conditions to the sub-query for nodes.
          if ($grants_exist) {
            $subquery->condition($grant_conditions);
          }
          $subquery->condition('na.grant_' . $operation, TRUE, '>=');

          // Add langcode-based filtering if this is a multilingual site.
          if ($is_multilingual) {
            // If no specific langcode to check for is given, use the grant entry
            // which is set as a fallback.
            // If a specific langcode is given, use the grant entry for it.
            if ($langcode === FALSE) {
              $subquery->condition('na.fallback', TRUE);
            }
            else {
              $subquery->condition('na.langcode', $langcode);
            }
          }

          $field = 'nid';
          // Now handle entities.
          $subquery->where("[$table_alias].[$field] = [na].[nid]");

          if (empty($tableinfo['join type'])) {
            $query->exists($subquery);
          }
          else {
            // If this is a join, add the node access check to the join condition.
            // This requires using $query->getTables() to alter the table
            // information.
            $join_cond = $query
              ->andConditionGroup()
              ->exists($subquery);
            $join_cond->where($tableinfo['condition'], $query->getTables()[$table_alias]['arguments']);
            $query->getTables()[$table_alias]['arguments'] = [];
            $query->getTables()[$table_alias]['condition'] = $join_cond;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function write(NodeInterface $node, array $grants, $realm = NULL, $delete = TRUE) {
    if ($delete) {
      $query = $this->database->delete('node_access')->condition('nid', (int) $node->id());
      if ($realm) {
        $query->condition('realm', [$realm, 'all'], 'IN');
      }
      $query->execute();
    }
    // Only perform work when node_access modules are active.
    if (!empty($grants) && $this->moduleHandler->hasImplementations('node_grants')) {
      $query = $this->database->insert('node_access')->fields(['nid', 'langcode', 'fallback', 'realm', 'gid', 'grant_view', 'grant_update', 'grant_delete']);
      // If we have defined a granted langcode, use it. But if not, add a grant
      // for every language this node is translated to.
      $fallback_langcode = $node->getUntranslated()->language()->getId();
      foreach ($grants as $grant) {
        if ($realm && $realm != $grant['realm']) {
          continue;
        }
        if (isset($grant['langcode'])) {
          $grant_languages = [$grant['langcode'] => $this->languageManager->getLanguage($grant['langcode'])];
        }
        else {
          $grant_languages = $node->getTranslationLanguages(TRUE);
        }
        foreach ($grant_languages as $grant_langcode => $grant_language) {
          // Only write grants; denies are implicit.
          if ($grant['grant_view'] || $grant['grant_update'] || $grant['grant_delete']) {
            $grant['nid'] = $node->id();
            $grant['langcode'] = $grant_langcode;
            // The record with the original langcode is used as the fallback.
            if ($grant['langcode'] == $fallback_langcode) {
              $grant['fallback'] = 1;
            }
            else {
              $grant['fallback'] = 0;
            }
            $query->values($grant);
          }
        }
      }
      $query->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $this->database->truncate('node_access')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function writeDefault() {
    $this->database->insert('node_access')
      ->fields([
        'nid' => 0,
        'realm' => 'all',
        'gid' => 0,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    if ($this->database->driver() == 'mongodb') {
      $prefixed_table = $this->database->getPrefix() . 'node_access';

      return (string) $this->database->getConnection()->selectCollection($prefixed_table)->count([], ['session' => $this->database->getMongodbSession()]);
    }
    else {
      return $this->database->query('SELECT COUNT(*) FROM {node_access}')->fetchField();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNodeRecords(array $nids) {
    // Make sure that all $nids have an integer value.
    foreach ($nids as &$nid) {
      $nid = (int) $nid;
    }

    $this->database->delete('node_access')
      ->condition('nid', $nids, 'IN')
      ->execute();
  }

  /**
   * Creates a query condition from an array of node access grants.
   *
   * @param array $node_access_grants
   *   An array of grants, as returned by node_access_grants().
   *
   * @return \Drupal\Core\Database\Query\Condition
   *   A condition object to be passed to $query->condition().
   *
   * @see node_access_grants()
   */
  protected function buildGrantsQueryCondition(array $node_access_grants) {
    $grants = $this->database->condition('OR');
    foreach ($node_access_grants as $realm => $gids) {
      if (!empty($gids)) {
        foreach ($gids as &$gid) {
          $gid = (int) $gid;
        }
        $and = $this->database->condition('AND');
        $grants->condition($and
          ->condition('gid', $gids, 'IN')
          ->condition('realm', $realm)
        );
      }
    }

    return $grants;
  }

}
