<?php

declare(strict_types=1);

namespace Drupal\cron_queue_test\Plugin\QueueWorker;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerWithMetadataInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cron_queue_test\Plugin\Derivative\CronQueueBackendTestDeriver;

/**
 * A queue worker for testing queue claimed count.
 */
#[QueueWorker(
  id: self::BASE_PLUGIN_ID,
  title: new TranslatableMarkup('Test queue count claimed'),
  cron: ['time' => 2],
  deriver: CronQueueBackendTestDeriver::class
)]
class CronQueueTestClaimCountQueue extends PluginBase implements QueueWorkerWithMetadataInterface {

  /**
   * The plugin ID.
   */
  public const BASE_PLUGIN_ID = 'cron_queue_test_claim_count';

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->processItemAndMetadata($data, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function processItemAndMetadata($data, ?object $metadata): void {
    $claimed_count = $metadata?->claimed_count ?? 0;
    if ($data === 'process') {
      return;
    }
    $state = \Drupal::state();
    $state->set($this->getPluginId(), $claimed_count);
    if ($claimed_count <= 2) {
      throw new DelayedRequeueException(5, "count = $claimed_count");
    }
    if ($claimed_count == 3) {
      throw new \Exception("count = $claimed_count");
    }
    // Claimed too many times, remove from queue.
  }

}
