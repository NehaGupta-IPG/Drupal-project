<?php

declare(strict_types=1);

namespace Drupal\Core\Batch;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Default implementation of the batch manager.
 *
 * This wraps two internal services: \Drupal\Core\Batch\BatchState, which owns
 * the batch data structure, and \Drupal\Core\Batch\BatchProcessor, which runs
 * it. Neither is part of the public API.
 */
class BatchManager implements BatchManagerInterface {

  public function __construct(
    protected readonly BatchState $batchState,
    protected readonly BatchProcessor $batchProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addBatch(BatchBuilder|array $batch): void {
    $this->batchState->add($batch instanceof BatchBuilder ? $batch->toArray() : $batch);
  }

  /**
   * {@inheritdoc}
   */
  public function needsProcessing(): bool {
    return !empty($this->batchState->get());
  }

  /**
   * {@inheritdoc}
   */
  public function setProgressive(bool $progressive): void {
    if (!$this->needsProcessing()) {
      throw new \LogicException('A batch must be added before its progressive flag can be set.');
    }
    $batch = &$this->batchState->get();
    $batch['progressive'] = $progressive;
  }

  /**
   * {@inheritdoc}
   */
  public function process(Url|string|null $redirect = NULL, ?Url $url = NULL, ?string $redirect_callback = NULL): ?RedirectResponse {
    return $this->batchProcessor->process($redirect, $url, $redirect_callback);
  }

}
