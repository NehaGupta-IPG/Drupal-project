<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides an interface for the batch processor service.
 */
interface BatchProcessorInterface {

  /**
   * Places the batch in the queue to be processed.
   *
   * Batch operations are added as new batch sets. Batch sets are used to spread
   * processing (primarily, but not exclusively, forms processing) over several
   * page requests. This helps to ensure that the processing is not interrupted
   * due to PHP timeouts, while users are still able to receive feedback on the
   * progress of the ongoing operations. Combining related operations into
   * distinct batch sets provides clean code independence for each batch set,
   * ensuring that two or more batches, submitted independently, can be
   * processed without mutual interference. Each batch set may specify its own
   * set of operations and results, produce its own UI messages, and trigger
   * its own 'finished' callback. Batch sets are processed sequentially, with
   * the progress bar starting afresh for each new set.
   *
   * @param array $batch_definition
   *   An associative array defining the batch. The array can be built by using
   *   the toArray() method of a populated \Drupal\Core\Batch\BatchBuilder
   *   object. It contains the following elements (all are optional except as
   *   noted):
   *   - operations: (required) Array of operations to be performed, where each
   *     item is an array consisting of the name of an implementation of
   *     callback_batch_operation() and an array of parameter.
   *     Example:
   *     @code
   *     [
   *       ['callback_batch_operation_1', [$arg1]],
   *       ['callback_batch_operation_2', [$arg2_1, $arg2_2]],
   *     ]
   *     @endcode
   *   - title: A safe, translated string to use as the title for the progress
   *     page. Defaults to t('Processing').
   *   - init_message: Message displayed while the processing is initialized.
   *     Defaults to t('Initializing.').
   *   - progress_message: Message displayed while processing the batch.
   *     Available placeholders are "@current", "@remaining", "@total",
   *     "@percentage",
   *     phpcs:ignore Drupal.Commenting.FunctionComment.ParamCommentFullStop
   *     "@estimate" and "@elapsed". Defaults to "t('Completed @current of
   *     @total.')".
   *   - error_message: Message displayed if an error occurred while
   *     processing the batch. Defaults to t('An error has occurred.').
   *   - finished: Name of an implementation of callback_batch_finished(). This
   *     is executed after the batch has completed. This should be used to
   *     perform any result massaging that may be needed, and possibly save
   *     data in the session for display after final page redirection.
   *   - file: Path to the file containing the definitions of the 'operations'
   *     and 'finished' functions, for instance if they don't reside in the
   *     main .module file. The path should be relative to base_path(), and
   *     thus should be built using ModuleExtensionList::getPath().
   *   - library: An array of batch-specific CSS and JS libraries.
   *   - url_options: options passed to the \Drupal\Core\Url object when
   *     constructing redirect URLs for the batch.
   *   - progressive: A Boolean that indicates whether or not the batch needs
   *     to run progressively. TRUE indicates that the batch will run in more
   *     than one run. FALSE (default) indicates that the batch will finish in
   *     a single run.
   *   - queue: An override of the default queue (with name and class fields
   *     optional). An array containing two elements:
   *     - name: Unique identifier for the queue.
   *     - class: The name of a class that implements
   *       \Drupal\Core\Queue\QueueInterface, including the full namespace but
   *       not starting with a backslash. It must have a constructor with two
   *       arguments: $name and a \Drupal\Core\Database\Connection object.
   *       Typically, the class will either be \Drupal\Core\Queue\Batch or
   *       \Drupal\Core\Queue\BatchMemory. Defaults to Batch if progressive is
   *       TRUE, or to BatchMemory if progressive is FALSE.
   *
   * @return $this
   *
   * @internal
   */
  public function queue(array $batch_definition): self;

  /**
   * Retrieves the current batch.
   *
   * @internal
   */
  public function &getCurrentBatch(): null|array|false;

  /**
   * Processes the batch.
   *
   * This function is generally not needed in form submit handlers;
   * Form API takes care of batches that were set during form submission.
   *
   * @param \Drupal\Core\Url|string|null $redirect
   *   (optional) Either path or Url object to redirect to when the batch has
   *   finished processing. Note that to simply force a batch to (conditionally)
   *   redirect to a custom location after it is finished processing but to
   *   otherwise allow the standard form API batch handling to occur, it is not
   *   necessary to call batch_process() and use this parameter. Instead, make
   *   the batch 'finished' callback return an instance of
   *   \Symfony\Component\HttpFoundation\RedirectResponse, which will be used
   *   automatically by the standard batch processing pipeline (and which takes
   *   precedence over this parameter).
   * @param \Drupal\Core\Url|null $url
   *   (optional) URL of the batch processing page. Should only be used for
   *   separate scripts like update.php.
   * @param string|null $redirect_callback
   *   (optional) Specify a function to be called to redirect to the progressive
   *   processing page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive. No return value
   *   otherwise.
   *
   * @internal
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse;

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
   *
   * @internal
   */
  public function appendSet(array &$batch, array $batch_set): void;

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
   *
   * @internal
   */
  public function queuePopulate(array &$batch, string $set_id): void;

  /**
   * Returns a queue object for a batch set.
   *
   * @param array $batch_set
   *   The batch set.
   *
   * @return \Drupal\Core\Queue\QueueInterface|null
   *   The queue object.
   *
   * @internal
   */
  public function getQueue(array $batch_set): ?QueueInterface;

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
   *
   * @internal
   */
  public function processQueue(): array|NULL|RedirectResponse|JsonResponse;

  /**
   * Returns the batch set being currently processed.
   *
   * @return array
   *   The current batch set.
   *
   * @internal
   */
  public function &getCurrentSet(): array;

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
   *
   * @internal
   */
  public function nextSet(): bool;

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
   *
   * @internal
   */
  public function finishedProcessing(): ?RedirectResponse;

  /**
   * Shutdown function: Stores the current batch data for the next request.
   *
   * @see _batch_page()
   * @see drupal_register_shutdown_function()
   * @internal
   */
  public function shutdown(): void;

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @param bool $new_value
   *   (optional) A new value to set.
   *
   * @return bool
   *   TRUE if the batch information needs to be updated; FALSE otherwise.
   *
   * @internal
   */
  public function needsUpdate(?bool $new_value = NULL): bool;

}
