<?php

namespace Drupal\mongodb\modules\comment;

use Drupal\comment\CommentInterface;
use Drupal\comment\CommentStatistics as CoreCommentStatistics;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * The MongoDB implementation of \Drupal\comment\CommentStatistics.
 */
class CommentStatistics extends CoreCommentStatistics {

  /**
   * {@inheritdoc}
   */
  public function getMaximumCount($entity_type) {
    $comment_count = 0;
    $result = $this->database->select('comment_entity_statistics', 'ces')
      ->fields('ces', ['comment_count'])
      ->condition('entity_type', $entity_type)
      ->execute()
      ->fetchAll();

    foreach ($result as $ces) {
      if (isset($ces->comment_count) && ($ces->comment_count > $comment_count)) {
        $comment_count = $ces->comment_count;
      }
    }
    return $comment_count;
  }

  /**
   * {@inheritdoc}
   */
  public function update(CommentInterface $comment) {
    // Allow bulk updates and inserts to temporarily disable the maintenance of
    // the {comment_entity_statistics} table.
    if (!$this->state->get('comment.maintain_entity_statistics')) {
      return;
    }

    $result = $this->database->select('comment', 'c')
      ->fields('c', ['cid'])
      ->condition('comment_translations.entity_id', (int) $comment->getCommentedEntityId())
      ->condition('comment_translations.entity_type', $comment->getCommentedEntityTypeId())
      ->condition('comment_translations.field_name', $comment->getFieldName())
      ->condition('comment_translations.status', (bool) CommentInterface::PUBLISHED)
      ->condition('comment_translations.default_langcode', TRUE)
      ->execute()
      ->fetchAll();
    $count = count($result);

    if ($count > 0) {
      // Comments exist.
      $last_reply = $this->database->select('comment', 'c')
        ->fields('c', ['comment_translations'])
        ->condition('comment_translations.entity_id', (int) $comment->getCommentedEntityId())
        ->condition('comment_translations.entity_type', $comment->getCommentedEntityTypeId())
        ->condition('comment_translations.field_name', $comment->getFieldName())
        ->condition('comment_translations.status', (bool) CommentInterface::PUBLISHED)
        ->condition('comment_translations.default_langcode', TRUE)
        ->orderBy('comment_translations.created', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchObject();

      foreach ($last_reply->comment_translations as $comment_translation) {
        if (($comment_translation['entity_id'] == $comment->getCommentedEntityId()) &&
          ($comment_translation['entity_type'] == $comment->getCommentedEntityTypeId()) &&
          ($comment_translation['field_name'] == $comment->getFieldName()) &&
          ($comment_translation['default_langcode'] == CommentInterface::PUBLISHED)) {
          $last_reply->cid = $comment_translation['cid'];
          $last_reply->name = $comment_translation['name'];
          $last_reply->changed = $comment_translation['changed'];
          $last_reply->uid = $comment_translation['uid'];
        }
      }
      unset($last_reply->comment_translations);

      // Use merge here because entity could be created before comment field.
      $this->database->merge('comment_entity_statistics')
        ->fields([
          'cid' => $last_reply->cid,
          'comment_count' => $count,
          'last_comment_timestamp' => $last_reply->changed,
          'last_comment_name' => $last_reply->uid ? '' : $last_reply->name,
          'last_comment_uid' => $last_reply->uid,
        ])
        ->keys([
          'entity_id' => (int) $comment->getCommentedEntityId(),
          'entity_type' => $comment->getCommentedEntityTypeId(),
          'field_name' => $comment->getFieldName(),
        ])
        ->execute();
    }
    else {
      // Comments do not exist.
      $entity = $comment->getCommentedEntity();
      // Get the user ID from the entity if it's set, or default to the
      // currently logged in user.
      if ($entity instanceof EntityOwnerInterface) {
        $last_comment_uid = $entity->getOwnerId();
      }
      if (!isset($last_comment_uid)) {
        // Default to current user when entity does not implement
        // EntityOwnerInterface or author is not set.
        $last_comment_uid = $this->currentUser->id();
      }
      $this->database->update('comment_entity_statistics')
        ->fields([
          'cid' => 0,
          'comment_count' => 0,
          // Use the changed date of the entity if it's set, or default to
          // $this->time->getRequestTime().
          'last_comment_timestamp' => ($entity instanceof EntityChangedInterface) ? (int) $entity->getChangedTimeAcrossTranslations() : $this->time->getRequestTime(),
          'last_comment_name' => '',
          'last_comment_uid' => (int) $last_comment_uid,
        ])
        ->condition('entity_id', (int) $comment->getCommentedEntityId())
        ->condition('entity_type', $comment->getCommentedEntityTypeId())
        ->condition('field_name', $comment->getFieldName())
        ->execute();
    }

    // Reset the cache of the commented entity so that when the entity is loaded
    // the next time, the statistics will be loaded again.
    $this->entityTypeManager->getStorage($comment->getCommentedEntityTypeId())->resetCache([$comment->getCommentedEntityId()]);
  }

}
