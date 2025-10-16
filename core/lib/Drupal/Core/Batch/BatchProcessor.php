<?php

namespace Drupal\Core\Batch;

use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Implements the default batch processor.
 *
 * @internal
 */
class BatchProcessor implements BatchProcessorInterface {

  /**
   * In memory batch cache.
   *
   * Not drupal_static(), because Batch API operates at a lower level than most
   * use-cases for resetting static variables, and we specifically do not want
   * a global drupal_static_reset() resetting the batch information. Functions
   * that are part of the Batch API and need to reset the batch information may
   * call BatchProcessorInterface::getCurrentBatch() and manipulate the result
   * by reference. Functions that are not part of the Batch API can also do
   * this, but shouldn't.
   *
   * @var array|null|false
   */
  protected static null|array|false $batch = [];

  /**
   * Checks whether the batch information needs to be updated in the storage.
   *
   * @var bool
   */
  protected static bool $needsUpdate = FALSE;

  /**
   * Creates a new BatchProcessor.
   *
   * @param string $root
   *   The app root.
   */
  public function __construct(
    protected readonly string $root,
  ) {
    require_once $root . '/core/includes/batch.inc';
    require_once $root . '/core/includes/form.inc';
  }

  /**
   * {@inheritdoc}
   */
  public function queue(array $batch_definition): void {
    batch_set($batch_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function appendSet(array &$batch, array $batch_set): void {
    _batch_append_set($batch, $batch_set);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function queuePopulate(array &$batch, string $set_id): void {
    _batch_populate_queue($batch, $set_id);
  }

  /**
   * {@inheritdoc}
   *
   * @internal
   */
  public function getQueue(array $batch_set): ?QueueInterface {
    return _batch_queue($batch_set);
  }

  /**
   * {@inheritdoc}
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse {
    return batch_process($redirect, $url, $redirect_callback);
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentBatch(): null|array|false {
    return static::$batch;
  }

  /**
   * {@inheritdoc}
   */
  public function processQueue(): array|NULL|RedirectResponse|JsonResponse {
    return _batch_process();
  }

  /**
   * {@inheritdoc}
   */
  public function &getCurrentSet(): array {
    return _batch_current_set();
  }

  /**
   * {@inheritdoc}
   */
  public function nextSet(): bool {
    return _batch_next_set();
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Form\FormSubmitter::submitForm()
   */
  public function finishedProcessing(): ?RedirectResponse {
    return _batch_finished();
  }

  /**
   * {@inheritdoc}
   */
  public function shutdown(): void {
    _batch_shutdown();
  }

  /**
   * {@inheritdoc}
   */
  public function needsUpdate(?bool $new_value = NULL): bool {
    if (isset($new_value)) {
      static::$needsUpdate = $new_value;
    }
    return static::$needsUpdate;
  }

}
