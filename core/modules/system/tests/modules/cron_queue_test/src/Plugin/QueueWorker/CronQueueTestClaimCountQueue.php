<?php

declare(strict_types=1);

namespace Drupal\cron_queue_test\Plugin\QueueWorker;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\DelayedRequeueException;
use Drupal\Core\Queue\QueueWorkerWithMetadataInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A queue worker for testing queue claimed count.
 */
#[QueueWorker(
  id: self::PLUGIN_ID,
  title: new TranslatableMarkup('Test queue count claimed'),
  cron: ['time' => 2]
)]
class CronQueueTestClaimCountQueue extends PluginBase implements QueueWorkerWithMetadataInterface {

  /**
   * The plugin ID.
   */
  public const PLUGIN_ID = 'cron_queue_test_claim_count';

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
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
    if ($claimed_count <= 2) {
      throw new DelayedRequeueException(5, "count = $claimed_count");
    }
    if ($claimed_count == 3) {
      //echo "FFF $claimed_count\n";
      throw new \Exception("count = $claimed_count");
    }
    // Claimed too many times, remove from queue.
  }

}
