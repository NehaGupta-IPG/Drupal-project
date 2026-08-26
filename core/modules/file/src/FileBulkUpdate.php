<?php

declare(strict_types=1);

namespace Drupal\file;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a service to update files in bulk.
 */
class FileBulkUpdate {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * Updates all files in the passed-in array with the passed-in field values.
   *
   * IMPORTANT NOTE: This function is intended to work when called from a form
   * submission handler. Calling it outside of the form submission process may
   * not work correctly.
   *
   * @param array $files
   *   Array of file IDs or files to update.
   * @param array $updates
   *   Array of key/value pairs with file field names and the value to update
   *   that field to.
   * @param bool $load
   *   (optional) TRUE if $files contains an array of file IDs to be loaded,
   *   FALSE if it contains fully loaded files. Defaults to FALSE.
   */
  public function process(array $files, array $updates, bool $load = FALSE): void {
    // We use batch processing to prevent timeout when updating a large number
    // of files.
    if (count($files) > 10) {
      $batch_builder = (new BatchBuilder())
        ->addOperation([static::class, 'batchProcess'], [$files, $updates, $load])
        ->setFinishCallback([static::class, 'batchFinished'])
        ->setTitle($this->t('Processing'))
        ->setErrorMessage($this->t('The update has encountered an error.'))
        // We use a single multi-pass operation, so the default
        // 'Remaining x of y operations' message will be confusing here.
        ->setProgressMessage('');
      batch_set($batch_builder->toArray());
    }
    else {
      $storage = $this->entityTypeManager->getStorage('file');
      if ($load) {
        $files = $storage->loadMultiple($files);
      }
      foreach ($files as $file) {
        static::processFile($file, $updates);
      }
      $this->messenger->addStatus($this->t('The update has been performed.'));
    }
  }

  /**
   * Updates individual files when fewer than 10 are queued.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file to update.
   * @param array $updates
   *   Associative array of updates.
   *
   * @return \Drupal\file\FileInterface
   *   An updated file object.
   *
   * @see self::process()
   */
  protected static function processFile(FileInterface $file, array $updates): FileInterface {
    // For efficiency manually save the original file before applying any
    // changes.
    $file->setOriginal(clone $file);
    foreach ($updates as $name => $value) {
      $file->$name = $value;
    }
    $file->save();
    return $file;
  }

  /**
   * Executes a batch operation for processing a file bulk update.
   *
   * @param array $files
   *   An array of file IDs.
   * @param array $updates
   *   Associative array of updates.
   * @param bool $load
   *   TRUE if $files contains an array of file IDs to be loaded, FALSE if it
   *   contains fully loaded files.
   * @param array|\ArrayAccess $context
   *   An array of contextual key/values.
   */
  public static function batchProcess(array $files, array $updates, bool $load, array|\ArrayAccess &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = count($files);
      $context['sandbox']['files'] = $files;
    }

    // Process files by groups of 5.
    $storage = \Drupal::entityTypeManager()->getStorage('file');
    $count = min(5, count($context['sandbox']['files']));
    for ($i = 1; $i <= $count; $i++) {
      // For each fid, load the file, reset the values, and save it.
      $file = array_shift($context['sandbox']['files']);
      if ($load) {
        $file = $storage->load($file);
      }
      $file = static::processFile($file, $updates);

      // Store result for post-processing in the finished callback.
      $context['results'][] = $file->label();

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
   * Reports the 'finished' status of batch operation the file bulk update.
   *
   * @param bool $success
   *   A boolean indicating whether the batch mass update operation successfully
   *   concluded.
   * @param string[] $results
   *   An array of file labels updated via the batch mode process.
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
