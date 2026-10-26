<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Queue\QueueInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Holds the batch of the current request and the queues backing its sets.
 *
 * This service owns the batch data structure. It only stores and manipulates
 * it; running the operations is the job of
 * \Drupal\Core\Batch\BatchProcessor, and callers outside the Batch API should
 * go through \Drupal\Core\Batch\BatchManagerInterface instead of either.
 *
 * This service must not depend on BatchProcessor: the dependency direction is
 * deliberately one-way, so that BatchManager can wrap both without a circular
 * reference.
 *
 * @internal
 *   This service is an implementation detail of
 *   \Drupal\Core\Batch\BatchManager. It is only public API for the Batch API
 *   itself and may change or be removed at any time.
 */
class BatchState {

  public function __construct(
    #[Autowire(param: 'app.root')]
    protected readonly string $root,
  ) {
    // \Drupal\Core\DrupalKernel::loadLegacyIncludes() loads form.inc, but not
    // batch.inc, and only runs during HTTP request handling. Load both files
    // here so the functions this service wraps are available in every context,
    // such as kernel tests and the command line.
    require_once $root . '/core/includes/form.inc';
    require_once $root . '/core/includes/batch.inc';
  }

  /**
   * Retrieves the current batch.
   *
   * The batch is returned by reference so that the Batch API can manipulate
   * the stored batch directly, for example to reset it once processing has
   * finished.
   *
   * @return array|null|false
   *   A reference to the current batch: an associative array containing the
   *   batch definition and its processing state, or an empty array if no batch
   *   has been set. NULL if a finished batch has been cleared, or FALSE if the
   *   batch could not be loaded from storage.
   */
  public function &get(): null|array|false {
    return batch_get();
  }

  /**
   * Adds a batch set to the current batch, initializing the batch if needed.
   *
   * @param array $batch_definition
   *   An associative array defining the batch.
   *
   * @see \Drupal\Core\Batch\BatchManagerInterface::addBatch()
   */
  public function add(array $batch_definition): void {
    batch_set($batch_definition);
  }

  /**
   * Appends a batch set to a running batch.
   *
   * Inserts the new set right after the current one to ensure execution order,
   * and stores its operations in a queue. If the current batch has already
   * inserted a new set, additional sets will be inserted after the last
   * inserted set.
   *
   * @param array $batch
   *   The batch array.
   * @param array $batch_set
   *   The batch set.
   */
  public function appendSet(array &$batch, array $batch_set): void {
    _batch_append_set($batch, $batch_set);
  }

  /**
   * Populates a job queue with the operations of a batch set.
   *
   * Depending on whether the batch is progressive or not, the
   * \Drupal\Core\Queue\Batch or \Drupal\Core\Queue\BatchMemory handler classes
   * will be used. The name and class of the queue are added by reference to
   * the batch set.
   *
   * @param array $batch
   *   The batch array.
   * @param string $set_id
   *   The id of the set to process.
   */
  public function populateQueue(array &$batch, string $set_id): void {
    _batch_populate_queue($batch, $set_id);
  }

  /**
   * Returns the queue object for a batch set.
   *
   * @param array $batch_set
   *   The batch set.
   *
   * @return \Drupal\Core\Queue\QueueInterface|null
   *   The queue object, or NULL if the set has no queue.
   */
  public function getQueue(array $batch_set): ?QueueInterface {
    return _batch_queue($batch_set);
  }

  /**
   * Returns the batch set being currently processed.
   *
   * @return array
   *   A reference to the current batch set.
   */
  public function &getCurrentSet(): array {
    return _batch_current_set();
  }

  /**
   * Advances to the next set in the batch.
   *
   * If there is a subsequent set in this batch, it is assigned as the new set
   * to process and its form submit handler (if defined) is executed, which may
   * add further sets to this batch.
   *
   * @return bool
   *   TRUE if a subsequent set was found in the batch, FALSE otherwise.
   */
  public function nextSet(): bool {
    return _batch_next_set();
  }

  /**
   * Checks whether the batch needs to be written back to the storage.
   *
   * @param bool|null $new_value
   *   (optional) A new value to set.
   *
   * @return bool
   *   TRUE if the batch information needs to be updated, FALSE otherwise.
   */
  public function needsUpdate(?bool $new_value = NULL): bool {
    return _batch_needs_update($new_value);
  }

}
