<?php

namespace Drupal\comment\Hook;

use Drupal\comment\CommentingStatus;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\RoleInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\CommentStatisticsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Hook implementations for comment.
 */
class CommentHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CommentManagerInterface $commentManager,
    private readonly CommentStatisticsInterface $commentStatistics,
    private readonly RendererInterface $renderer,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_node_update_index().
   */
  #[Hook('node_update_index')]
  public function nodeUpdateIndex(EntityInterface $node): string {
    $index_comments =& drupal_static('comment_node_update_index');
    if ($index_comments === NULL) {
      // Do not index in the following three cases:
      // 1. 'Authenticated user' can search content but can't access comments.
      // 2. 'Anonymous user' can search content but can't access comments.
      // 3. Any role can search content but can't access comments and access
      // comments is not granted by the 'authenticated user' role. In this case
      // all users might have both permissions from various roles but it is also
      // possible to set up a user to have only search content and so a user
      // edit could change the security situation so it is not safe to index the
      // comments.
      $index_comments = TRUE;
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      $authenticated_can_access = $roles[RoleInterface::AUTHENTICATED_ID]->hasPermission('access comments');
      foreach ($roles as $rid => $role) {
        if ($role->hasPermission('search content') && !$role->hasPermission('access comments')) {
          if ($rid == RoleInterface::AUTHENTICATED_ID || $rid == RoleInterface::ANONYMOUS_ID || !$authenticated_can_access) {
            $index_comments = FALSE;
            break;
          }
        }
      }
    }
    $build = [];
    if ($index_comments) {
      foreach ($this->commentManager->getFields('node') as $field_name => $info) {
        // Skip fields that entity does not have.
        if (!$node->hasField($field_name)) {
          continue;
        }
        $field_definition = $node->getFieldDefinition($field_name);
        $mode = $field_definition->getSetting('default_mode');
        $comments_per_page = $field_definition->getSetting('per_page');
        if ($node->get($field_name)->status) {
          $comments = $this->entityTypeManager->getStorage('comment')->loadThread($node, $field_name, $mode, $comments_per_page);
          if ($comments) {
            $build[] = $this->entityTypeManager->getViewBuilder('comment')->viewMultiple($comments);
          }
        }
      }
    }
    return $this->renderer->renderInIsolation($build);
  }

  /**
   * Implements hook_node_search_result().
   *
   * Formats a comment count string and returns it, for display with search
   * results.
   */
  #[Hook('node_search_result')]
  public function nodeSearchResult(EntityInterface $node): array {
    $comment_fields = $this->commentManager->getFields('node');
    $comments = 0;
    $open = FALSE;
    foreach ($comment_fields as $field_name => $info) {
      // Skip fields that entity does not have.
      if (!$node->hasField($field_name)) {
        continue;
      }
      // Do not make a string if comments are hidden.
      $status = CommentingStatus::tryFrom((int) $node->get($field_name)->status);
      if ($this->currentUser->hasPermission('access comments') && $status != CommentingStatus::Hidden) {
        if ($status == CommentingStatus::Open) {
          // At least one comment field is open.
          $open = TRUE;
        }
        $comments += $node->get($field_name)->comment_count;
      }
    }
    // Do not make a string if there are no comment fields, or no comments exist
    // or all comment fields are hidden.
    if ($comments > 0 || $open) {
      return [
        'comment' => $this->stringTranslation->formatPlural(
          $comments,
          '1 comment',
          '@count comments'
        ),
      ];
    }
    return [];
  }

  /**
   * Implements hook_node_search_ranking().
   */
  #[Hook('node_search_ranking')]
  public function ranking(): array {
    return $this->commentStatistics->getRankingInfo();
  }

}
