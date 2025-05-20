<?php

namespace Drupal\Core\Queue;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Defines an interface for a QueueWorker plugin.
 *
 * @see \Drupal\Core\Queue\QueueWorkerBase
 * @see \Drupal\Core\Queue\QueueWorkerManager
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see plugin_api
 *
 * @ingroup queue
 */
interface QueueWorkerInterface extends PluginInspectionInterface {

  /**
   * Works on a single queue item.
   *
   * @param mixed $data
   *   The data that was passed to
   *   \Drupal\Core\Queue\QueueInterface::createItem() when the item was queued.
   * phpcs:disable Drupal.Commenting
   * @todo Uncomment new method parameter before drupal:12.0.0.
   * @see https://www.drupal.org/project/drupal/issues/3525399
   *
   * @param object|null $metadata
   *   The queue item metadata, typically including the created time and claimed
   *   count. The worker must still work correctly if this is NULL or expected
   *   properties are missing.
   * phpcs:enable
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Processing is not yet finished. This will allow another process to claim
   *   the item immediately.
   * @throws \Exception
   *   A QueueWorker plugin may throw an exception to indicate there was a
   *   problem. The cron process will log the exception, and leave the item in
   *   the queue to be processed again later.
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   More specifically, a SuspendQueueException should be thrown when a
   *   QueueWorker plugin is aware that the problem will affect all subsequent
   *   workers of its queue. For example, a callback that makes HTTP requests
   *   may find that the remote server is not responding. The cron process will
   *   behave as with a normal Exception, and in addition will not attempt to
   *   process further items from the current item's queue during the current
   *   cron run.
   * @throws \Drupal\Core\Queue\DelayedRequeueException
   *   To leave an item in the queue until its lock expires.
   *
   * @see \Drupal\Core\Cron::processQueues()
   */
  public function processItem($data, /* ?object $metadata = NULL */);

}
