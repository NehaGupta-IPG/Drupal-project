<?php

declare(strict_types=1);

namespace Drupal\comment;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a service to update comments in bulk.
 */
class CommentBulkUpdate {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * Updates all comments in the passed-in array with the passed-in field
   * values.
   *
   * IMPORTANT NOTE: This function is intended to work when called from a form
   * submission handler. Calling it outside of the form submission process may
   * not work correctly.
   *
   * @param array $comments
   *   Array of comment cids or comments to update.
   * @param array $updates
   *   Array of key/value pairs with comment field names and the value to update
   *   that field to.
   * @param string|null $langcode
   *   (optional) The language updates should be applied to. If NULL all
   *   available languages are processed.
   * @param bool $load
   *   (optional) TRUE if $comments contains an array of comment IDs to be
   *   loaded, FALSE if it contains fully loaded comments. Defaults to FALSE.
   * @param bool $revisions
   *   (optional) TRUE if $comments contains an array of revision IDs instead of
   *   comment IDs. Defaults to FALSE; will be ignored if $load is FALSE.
   */
  public function process(array $comments, array $updates, ?string $langcode = NULL, bool $load = FALSE, bool $revisions = FALSE): void {
    // We use batch processing to prevent timeout when updating a large number
    // of comments.
    if (count($comments) > 10) {
      $batch_builder = (new BatchBuilder())
        ->addOperation([static::class, 'batchProcess'], [$comments, $updates, $langcode, $load, $revisions])
        ->setFinishCallback([static::class, 'batchFinished'])
        ->setTitle($this->t('Processing'))
        ->setErrorMessage($this->t('The update has encountered an error.'))
        // We use a single multi-pass operation, so the default
        // 'Remaining x of y operations' message will be confusing here.
        ->setProgressMessage('');
      batch_set($batch_builder->toArray());
    }
    else {
      /** @var \Drupal\comment\CommentStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage('comment');
      if ($load) {
        $comments = $revisions ? $storage->loadMultipleRevisions($comments) : $storage->loadMultiple($comments);
      }
      foreach ($comments as $comment) {
        static::processComment($comment, $updates, $langcode);
      }
      $this->messenger->addStatus($this->t('The update has been performed.'));
    }
  }

  /**
   * Updates individual comments when fewer than 10 are queued.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   A comment to update.
   * @param array $updates
   *   Associative array of updates.
   * @param string|null $langcode
   *   (optional) The language updates should be applied to. If NULL all
   *   available languages are processed.
   *
   * @return \Drupal\comment\CommentInterface
   *   An updated comment object.
   *
   * @see self::process()
   */
  protected static function processComment(CommentInterface $comment, array $updates, string|null $langcode = NULL): CommentInterface {
    $langcodes = isset($langcode) ? [$langcode] : array_keys($comment->getTranslationLanguages());
    // For efficiency manually save the original comment before applying any
    // changes.
    $comment->setOriginal(clone $comment);
    foreach ($langcodes as $langcode) {
      foreach ($updates as $name => $value) {
        $comment->getTranslation($langcode)->$name = $value;
      }
    }
    $comment->save();
    return $comment;
  }

  /**
   * Executes a batch operation for processing a comment bulk update.
   *
   * @param array $comments
   *   An array of comment IDs.
   * @param array $updates
   *   Associative array of updates.
   * @param string|null $langcode
   *   The language updates should be applied to. If none is specified all
   *   available languages are processed.
   * @param bool $load
   *   TRUE if $comments contains an array of comment IDs to be loaded, FALSE if
   *   it contains fully loaded comments.
   * @param bool $revisions
   *   (optional) TRUE if $comments contains an array of revision IDs instead of
   *   comment IDs. Defaults to FALSE; will be ignored if $load is FALSE.
   * @param array|\ArrayAccess $context
   *   An array of contextual key/values.
   */
  public static function batchProcess(array $comments, array $updates, ?string $langcode, bool $load, bool $revisions, array|\ArrayAccess &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($comments);
      $context['sandbox']['comments'] = $comments;
    }

    // Process comments by groups of 5.
    $storage = \Drupal::entityTypeManager()->getStorage('comment');
    $count = min(5, count($context['sandbox']['comments']));
    for ($i = 1; $i <= $count; $i++) {
      // For each cid, load the comment, reset the values, and save it.
      $comment = array_shift($context['sandbox']['comments']);
      if ($load) {
        $comment = $revisions ? $storage->loadRevision($comment) : $storage->load($comment);
      }
      $comment = static::processComment($comment, $updates, $langcode);

      // Store result for post-processing in the finished callback.
      $context['results'][] = $comment->toLink()->toString();

      // Update our progress information.
      $context['sandbox']['progress']++;
    }

    // Inform the batch engine that we are not finished, and provide an
    // estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
  }

  /**
   * Reports the 'finished' status of batch operation the comment bulk update.
   *
   * @param bool $success
   *   A boolean indicating whether the batch mass update operation successfully
   *   concluded.
   * @param string[] $results
   *   An array of rendered links to comments updated via the batch mode
   *   process.
   *
   * @see self::batchProcess()
   */
  public static function batchFinished(bool $success, array $results): void {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addStatus(t('The update has been performed.'));
    }
    else {
      $messenger->addError(t('An error occurred and processing did not complete.'));
      $message = \Drupal::translation()->formatPlural(count($results), '1 item successfully processed:', '@count items successfully processed:');
      $item_list = [
        '#theme' => 'item_list',
        '#items' => $results,
      ];
      $message .= \Drupal::service('renderer')->render($item_list);
      $messenger->addStatus($message);
    }
  }

}
