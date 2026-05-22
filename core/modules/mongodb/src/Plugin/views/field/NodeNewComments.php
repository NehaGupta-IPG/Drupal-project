<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\comment\CommentInterface;
use Drupal\comment\Plugin\views\field\NodeNewComments as CoreNodeNewComments;
use MongoDB\BSON\UTCDateTime;

/**
 * Overriding the views field plugin "node_new_comments".
 */
class NodeNewComments extends CoreNodeNewComments {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$values) {
    $user = \Drupal::currentUser();
    if ($user->isAnonymous() || empty($values)) {
      return;
    }

    $nids = [];
    $ids = [];
    foreach ($values as $id => $result) {
      $nids[] = (int) $result->{$this->aliases['nid']};
      $values[$id]->{$this->field_alias} = 0;
      // Create a reference so we can find this record in the values again.
      if (empty($ids[$result->{$this->aliases['nid']}])) {
        $ids[$result->{$this->aliases['nid']}] = [];
      }
      $ids[$result->{$this->aliases['nid']}][] = $id;
    }

    if ($nids) {
      // $history_read_limit = new UTCDateTime(HISTORY_READ_LIMIT * 1000);
      $query = $this->database->select('node', 'n')
        ->fields('n', ['nid']);
      $query->addJoin('INNER', 'comment', 'c', $query->joinCondition()
        ->compare('c.comment_translations.entity_id', 'n.nid')
        ->condition('c.comment_translations.entity_type', 'node')
        ->condition('c.comment_translations.status', (bool) CommentInterface::PUBLISHED)
        ->condition('c.comment_translations.default_langcode', TRUE)
      );
      $query->fields('c', ['cid', 'comment_translations']);
      $query->addJoin('LEFT', 'history', 'h', $query->joinCondition()
        ->compare('n.nid', 'h.nid')
        ->condition('h.uid', (int) $user->id())
      );
      $query->fields('h', ['timestamp']);
      $query->condition('n.nid', $nids, 'IN');
      // $query->addCoalesceValueField('history_timestamp1', 'h.timestamp', $history_read_limit);
      // $query->addGreatestField('history_timestamp2', ['history_timestamp1'], [$history_read_limit]);
      // The next does not work, but it should.
      // $query->compare('c.comment_translations.changed', 'history_timestamp2', '>');
      $result = $query->execute()->fetchAll();

      $comment_count = [];
      foreach ($result as $row) {
        $is_new = FALSE;

        // Get the comment changed time.
        $comment_translations = $row->c_comment_translations ?? [];
        $comment_changed = $comment_translations['changed'] ?? 0;
        if ($comment_changed instanceof UTCDateTime) {
          $comment_changed = (int) $comment_changed->__toString();
          $comment_changed = $comment_changed / 1000;
          $comment_changed = (string) $comment_changed;
        }

        // Get the history timestamp.
        $history_timestamp = $row->h_timestamp ?? FALSE;
        if ($history_timestamp) {
          if ($history_timestamp instanceof UTCDateTime) {
            $history_timestamp = (int) $history_timestamp->__toString();
            $history_timestamp = $history_timestamp / 1000;
            $history_timestamp = (string) $history_timestamp;
          }
          // Add the comment as "new" when the comment changed time is newer
          // then the history timestamp.
          if ($comment_changed > $history_timestamp) {
            $is_new = TRUE;
          }
        }
        // Add the comment as "new" when the comment changed time is newer
        // then the history read limit.
        elseif ($comment_changed > HISTORY_READ_LIMIT) {
          $is_new = TRUE;
        }

        // Only add the comment to the count when it is a "new" comment.
        if ($is_new) {
          if (isset($comment_count[$row->nid])) {
            $comment_count[$row->nid]++;
          }
          else {
            $comment_count[$row->nid] = 1;
          }
        }
      }

      foreach ($comment_count as $nid => $num_comments) {
        foreach ($ids[$nid] as $id) {
          $values[$id]->{$this->field_alias} = $num_comments;
        }
      }
    }
  }

}
