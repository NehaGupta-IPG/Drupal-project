<?php

namespace Drupal\mongodb\modules\node;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;

/**
 * The MongoDB implementation of \Drupal\node\NodeStorage.
 */
class NodeStorage extends SqlContentEntityStorage implements NodeStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(NodeInterface $node) {
    $results = $this->database->select('node')
      ->fields('node', ['node_all_revisions.vid'])
      ->condition('nid', (int) $node->id())
      ->execute()
      ->fetchCol();
    $result = reset($results);
    sort($result);
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    $results = $this->database->select('node', 'n')
      ->fields('n', ['node_all_revisions'])
      ->condition('node_all_revisions.uid', (int) $account->id())
      ->execute()
      ->fetchAll();

    $vids = [];
    foreach ($results as $node) {
      foreach ($node->node_all_revisions as $revision) {
        if ($revision['uid'] == $account->id()) {
          $vids[] = (int) $revision['vid'];
        }
      }
    }

    $vids = array_unique($vids);
    sort($vids);
    return $vids;
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(NodeInterface $node) {
    $node_all_revisions = $this->database->select('node', 'n')
      ->fields('n', ['node_all_revisions'])
      ->condition('nid', (int) $node->id())
      ->execute()
      ->fetchField();
    $count = 0;
    foreach ($node_all_revisions as $node_all_revision) {
      if (isset($node_all_revision['default_langcode']) && ($node_all_revision['default_langcode'] == 1)) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function updateType($old_type, $new_type) {
    return $this->database->update($this->getBaseTable())
      ->fields(['type' => $new_type])
      ->condition('type', $old_type)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    // @todo This is something that is not easy to do with MongoDB. We need to
    // check every revision from every node to see if it needs to be updated.
  }

}
