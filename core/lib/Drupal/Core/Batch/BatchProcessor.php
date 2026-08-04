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
   * {@inheritdoc}
   */
  public function appendSet(array &$batch, array $batch_set): void {
    _batch_append_set($batch, $batch_set);
  }

  /**
   * {@inheritdoc}
   */
  public function queuePopulate(array &$batch, string $set_id): void {
    _batch_populate_queue($batch, $set_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue(array $batch_set): ?QueueInterface {
    return _batch_queue($batch_set);
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
    return _batch_needs_update($new_value);
  }

}
