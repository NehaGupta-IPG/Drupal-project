<?php

namespace Drupal\Core\Queue;

use Drupal\Component\Plugin\PluginBase;

/**
 * Provides a base implementation for a QueueWorker plugin.
 *
 * @see \Drupal\Core\Queue\QueueWorkerInterface
 * @see \Drupal\Core\Queue\QueueWorkerManager
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see plugin_api
 *
 * @ingroup queue
 */
abstract class QueueWorkerBase extends PluginBase implements QueueWorkerWithMetadataInterface {

  /**
   * {@inheritdoc}
   */
  public function processItemAndMetadata($data, ?object $metadata): void {
    // By default, we can ignore the extra metadata.
    $this->processItem($data);
  }

}
