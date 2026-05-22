<?php

namespace Drupal\mongodb\modules\node;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeGrantDatabaseStorage as CoreNodeGrantDatabaseStorage;
use Drupal\node\NodeInterface;

/**
 * The MongoDB implementation of \Drupal\node\NodeGrantDatabaseStorage.
 */
class NodeGrantDatabaseStorage extends CoreNodeGrantDatabaseStorage {

  /**
   * {@inheritdoc}
   */
  public function access(NodeInterface $node, $operation, AccountInterface $account) {
    if (!in_array($operation, ['view', 'update', 'delete'])) {
      return AccessResult::neutral();
    }

    // If no module implements the hook or the node does not have an id there is
    // no point in querying the database for access grants.
    if (!$this->moduleHandler->hasImplementations('node_grants') || !$node->id()) {
      // Return the equivalent of the default grant, defined by
      // self::writeDefault().
      if ($operation === 'view') {
        return AccessResult::allowedIf($node->isPublished());
      }
      else {
        return AccessResult::neutral();
      }
    }

    // Check the database for potential access grants.
    $query = $this->database->select('node_access');
    $query->fields('node_access', ['nid', 'langcode', 'gid', 'realm']);
    // Only interested for granting in the current operation.
    $query->condition('grant_' . $operation, TRUE);

    // Check for grants for this node and the correct langcode.
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

    if ($query->execute()->fetchObject()) {
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
    $query->fields('node_access', ['nid', 'langcode', 'gid', 'realm']);
    $query
      ->condition('nid', 0)
      ->condition('grant_view', TRUE);

    $grants = $this->buildGrantsQueryCondition(node_access_grants('view', $account));

    if (count($grants) > 0) {
      $query->condition($grants);
    }

    $count = $query->execute()->fetchAll();
    return count($count);
  }

  /**
   * {@inheritdoc}
   */
  public function alterQuery($query, array $tables, $operation, AccountInterface $account, $base_table) {
    if (!$langcode = $query->getMetaData('langcode')) {
      $langcode = FALSE;
    }

    // Find all instances of the base table being joined -- could appear
    // more than once in the query, and could be aliased. Join each one to
    // the node_access table.
    $grants = node_access_grants($operation, $account);
    // If any grant exists for the specified user, then user has access to the
    // node for the specified operation.
    $grant_conditions = $this->buildGrantsQueryCondition($grants);
    $grants_exist = count($grant_conditions->conditions()) > 0;

    $is_multilingual = \Drupal::languageManager()->isMultilingual();
    foreach ($tables as $tableinfo) {
      $table = $tableinfo['table'];
      if (!($table instanceof SelectInterface) && $table == $base_table) {

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
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function write(NodeInterface $node, array $grants, $realm = NULL, $delete = TRUE) {
    if ($delete) {
      // The only change in this method for MongoDB is the to integer casting.
      $query = $this->database->delete('node_access')->condition('nid', (int) $node->id());
      if ($realm) {
        $query->condition('realm', [$realm, 'all'], 'IN');
      }
      $query->execute();
    }
    // Only perform work when node_access modules are active.
    if (!empty($grants) && $this->moduleHandler->hasImplementations('node_grants')) {
      $query = $this->database->insert('node_access')->fields([
        'nid',
        'langcode',
        'fallback',
        'realm',
        'gid',
        'grant_view',
        'grant_update',
        'grant_delete',
      ]);
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
  public function count() {
    $prefixed_table = $this->database->getPrefix() . 'node_access';

    return (string) $this->database->getConnection()->selectCollection($prefixed_table)->count([], ['session' => $this->database->getMongodbSession()]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteNodeRecords(array $nids) {
    // Make sure that all $nids have an integer value.
    foreach ($nids as &$nid) {
      $nid = (int) $nid;
    }
    parent::deleteNodeRecords($nids);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildGrantsQueryCondition(array $node_access_grants, $is_subquery = FALSE) {
    if ($is_subquery) {
      $conditions = [];
    }
    else {
      $grants = $this->database->condition("OR");
    }
    foreach ($node_access_grants as $realm => $gids) {
      if (!empty($gids)) {
        foreach ($gids as &$gid) {
          $gid = (int) $gid;
        }
        if ($is_subquery) {
          $conditions[] = [
            '$and' => [
              ['$in' => ['$gid', $gids]],
              ['$eq' => ['$realm', $realm]],
            ],
          ];
        }
        else {
          $and = $this->database->condition('AND');
          $grants->condition($and
            ->condition('gid', $gids, 'IN')
            ->condition('realm', $realm)
          );
        }
      }
    }

    if ($is_subquery) {
      return $conditions;
    }
    else {
      return $grants;
    }
  }

}
