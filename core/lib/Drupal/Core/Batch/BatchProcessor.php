<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines the BatchProcessor service.
 *
 * @internal
 */
class BatchProcessor implements BatchProcessorInterface {

  public function __construct(
    #[Autowire(param: 'app.root')]
    protected readonly string $root,
  ) {
    // \Drupal\Core\DrupalKernel::loadLegacyIncludes() loads form.inc, but
    // not batch.inc, and only runs during HTTP request handling. Load both
    // files here so the functions this service wraps are available in every
    // context, such as kernel tests and the command line.
    require_once $root . '/core/includes/batch.inc';
    require_once $root . '/core/includes/form.inc';
  }

  /**
   * {@inheritdoc}
   */
  public function queue(array $batch_definition): self {
    batch_set($batch_definition);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentBatch(): null|array|false {
    return batch_get();
  }

  /**
   * {@inheritdoc}
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse {
    return batch_process($redirect, $url, $redirect_callback);
  }

  /**
   * Appends a batch set to a running batch.
   *
   * Inserts the new set right after the current one to ensure execution order,
   * and stores its operations in a queue. If the current batch has already
   * inserted a new set, additional sets will be inserted after the last
   * inserted set.
   *
   * @param array &$batch
   *   The batch array.
   * @param array $batch_set
   *   The batch set.
   */
  protected function appendSet(array &$batch, array $batch_set): void {
    _batch_append_set($batch, $batch_set);
  }

  /**
   * Populates a job queue with the operations of a batch set.
   *
   * Depending on whether the batch is progressive or not, the
   * Drupal\Core\Queue\Batch or Drupal\Core\Queue\BatchMemory handler classes
   * will be used. The name and class of the queue are added by reference to the
   * batch set.
   *
   * @param array $batch
   *   The batch array.
   * @param string $set_id
   *   The id of the set to process.
   */
  protected function queuePopulate(array &$batch, string $set_id): void {
    _batch_populate_queue($batch, $set_id);
  }

  /**
   * Returns a queue object for a batch set.
   *
   * @param array $batch_set
   *   The batch set.
   *
   * @return \Drupal\Core\Queue\QueueInterface|null
   *   The queue object.
   */
  protected function getQueue(array $batch_set): ?QueueInterface {
    return _batch_queue($batch_set);
  }

  /**
   * Processes sets in a batch.
   *
   * If the batch was marked for progressive execution (default), this executes
   * as many operations in batch sets until an execution time of 1 second has
   * been exceeded. It will continue with the next operation of the same batch
   * set in the next request.
   *
   * @return array
   *   An array containing a completion value (in percent) and a status message.
   */
  protected function processQueue(): array|NULL|RedirectResponse|JsonResponse {
    return _batch_process();
  }

  /**
   * Returns the batch set being currently processed.
   *
   * @return array
   *   The current batch set.
   */
  protected function &getCurrentSet(): array {
    return _batch_current_set();
  }

  /**
   * Retrieves the next set in a batch.
   *
   * If there is a subsequent set in this batch, assign it as the new set to
   * process and execute its form submit handler (if defined), which may add
   * further sets to this batch.
   *
   * @return bool
   *   TRUE if a subsequent set was found in the batch; FALSE will be returned
   *   if no subsequent set was found.
   */
  protected function nextSet(): bool {
    return _batch_next_set();
  }

  /**
   * Ends the batch processing.
   *
   * Call the 'finished' callback of each batch set to allow custom handling of
   * the results and resolve page redirection.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response to the completed page or NULL to stay at the current
   *   URL.
   *
   * @see \Drupal\Core\Form\FormSubmitter::submitForm()
   */
  protected function finishedProcessing(): ?RedirectResponse {
    return _batch_finished();
  }

  /**
   * Shutdown function: Stores the current batch data for the next request.
   *
   * @see _batch_page()
   * @see drupal_register_shutdown_function()
   */
  protected function shutdown(): void {
    _batch_shutdown();
  }

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @param bool $new_value
   *   (optional) A new value to set.
   *
   * @return bool
   *   TRUE if the batch information needs to be updated; FALSE otherwise.
   */
  protected function needsUpdate(?bool $new_value = NULL): bool {
    return _batch_needs_update($new_value);
  }

}
