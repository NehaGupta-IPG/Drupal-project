<?php

declare(strict_types=1);

namespace Drupal\node\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeBulkUpdate;
use Drupal\user\UserInterface;

/**
 * Hook implementations for the node module.
 */
class NodeUserHooks {

  /**
   * NodeHooks constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected NodeBulkUpdate $nodeBulkUpdate,
  ) {
  }

  /**
   * Implements hook_user_cancel().
   *
   * Unpublish nodes (current revisions).
   */
  #[Hook('user_cancel')]
  public function userCancelBlockUnpublish($edit, UserInterface $account, $method): void {
    if ($method === 'user_cancel_block_unpublish') {
      $nids = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('uid', $account->id())
        ->execute();
      $this->nodeBulkUpdate->process($nids, ['status' => 0], NULL, TRUE);
    }
  }

  /**
   * Implements hook_user_cancel().
   *
   * Anonymize all of the nodes for this old account.
   */
  #[Hook('user_cancel')]
  public function userCancelReassign($edit, UserInterface $account, $method): void {
    if ($method === 'user_cancel_reassign') {
      // Reassign node ownership.
      // Since the translation revision creates a new record for each
      // language, we need to reassign the ownership in the context of
      // certain language, to not override author for other ones.
      $revision_ids_by_language = $this->userRevisionIdsGroupedByLanguage($account);
      foreach ($revision_ids_by_language as $language => $revision_ids) {
        $this->nodeBulkUpdate->process($revision_ids, ['uid' => 0], $language, TRUE, TRUE, TRUE);
      }
      // Reassign revision authors.
      $revision_ids = $this->userRevisionIdsByRevisionAuthor($account);
      if (!empty($revision_ids)) {
        $this->nodeBulkUpdate->process($revision_ids, ['revision_uid' => 0], NULL, TRUE, TRUE, TRUE);
      }
    }
  }

  /**
   * Returns a list of revision IDs grouped by language for a specific author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return array<string, int[]>
   *   Node revision IDs (in ascending order).
   */
  protected function userRevisionIdsGroupedByLanguage(AccountInterface $account) {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $query = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('uid', $account->id())
      ->sort('vid');
    $revision_ids = array_keys($query->execute());

    $grouped = [];
    foreach ($revision_ids as $revision_id) {
      $revision = $nodeStorage->loadRevision($revision_id);
      if ($revision) {
        // The revision may have translations which are not authored by our
        // user.
        foreach ($revision->getTranslationLanguages() as $langcode => $language) {
          $revisionInLanguage = $revision->getTranslation($langcode);
          if ($revisionInLanguage->getOwnerId() == $account->id()) {
            $grouped[$langcode][] = $revision_id;
          }
        }
      }
    }

    return $grouped;
  }

  /**
   * Returns a list of revision IDs having a given user as revision author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Node revision IDs (in ascending order).
   */
  protected function userRevisionIdsByRevisionAuthor(AccountInterface $account): array {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('revision_uid', $account->id())
      ->sort('vid');

    return array_keys($query->execute());
  }

}
