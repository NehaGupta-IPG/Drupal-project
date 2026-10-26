<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Manages the batch of the current request.
 *
 * This is the entry point to the Batch API for code outside of it. It covers
 * adding a batch, inspecting whether one is pending, and starting to process
 * it. It deliberately does not expose the batch data structure itself.
 *
 * @see \Drupal\Core\Batch\BatchBuilder
 * @see batch
 */
interface BatchManagerInterface {

  /**
   * Adds a batch to be processed.
   *
   * Batch operations are added as new batch sets. Batch sets are used to
   * spread processing (primarily, but not exclusively, forms processing) over
   * several page requests. This helps to ensure that the processing is not
   * interrupted due to PHP timeouts, while users are still able to receive
   * feedback on the progress of the ongoing operations. Combining related
   * operations into distinct batch sets provides clean code independence for
   * each batch set, ensuring that two or more batches, submitted
   * independently, can be processed without mutual interference. Each batch
   * set may specify its own set of operations and results, produce its own UI
   * messages, and trigger its own 'finished' callback. Batch sets are
   * processed sequentially, with the progress bar starting afresh for each new
   * set.
   *
   * @param \Drupal\Core\Batch\BatchBuilder|array $batch
   *   A populated batch builder, or an associative array defining the batch.
   *   The array contains the following elements (all are optional except as
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
   *   - error_message: Message displayed if an error occurred while processing
   *     the batch. Defaults to t('An error has occurred.').
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
   */
  public function addBatch(BatchBuilder|array $batch): void;

  /**
   * Checks whether a batch is waiting to be processed.
   *
   * @return bool
   *   TRUE if a batch has been added and not processed yet, FALSE otherwise.
   */
  public function needsProcessing(): bool;

  /**
   * Sets whether the pending batch runs progressively.
   *
   * A progressive batch is spread over several requests and shows a progress
   * bar. A non-progressive batch runs in a single request, which bypasses both
   * the progress bar and the redirect.
   *
   * @param bool $progressive
   *   TRUE to run the batch progressively, FALSE to run it in a single
   *   request.
   *
   * @throws \LogicException
   *   Thrown when no batch has been added yet.
   */
  public function setProgressive(bool $progressive): void;

  /**
   * Processes the pending batch.
   *
   * This method is generally not needed in form submit handlers. Form API
   * takes care of batches that were added during form submission.
   *
   * @param \Drupal\Core\Url|string|null $redirect
   *   (optional) Either a path or a Url object to redirect to when the batch
   *   has finished processing. To simply force a batch to (conditionally)
   *   redirect to a custom location after it is finished processing, but to
   *   otherwise allow the standard form API batch handling to occur, it is not
   *   necessary to call this method and use this parameter. Instead, make the
   *   batch 'finished' callback return an instance of
   *   \Symfony\Component\HttpFoundation\RedirectResponse, which is used
   *   automatically by the standard batch processing pipeline, and which takes
   *   precedence over this parameter. If this parameter is omitted and no
   *   redirect response was returned by the 'finished' callback, the user is
   *   redirected to the page that started the batch. Any query arguments are
   *   automatically persisted.
   * @param \Drupal\Core\Url|null $url
   *   (optional) URL of the batch processing page. Should only be used for
   *   separate scripts like update.php.
   * @param string|null $redirect_callback
   *   (optional) A callable to be called to redirect to the progressive
   *   processing page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|null
   *   A redirect response if the batch is progressive, NULL otherwise.
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse;

}
