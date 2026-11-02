<?php

namespace Drupal\pgsql\Queue;

use Drupal\Core\Queue\DatabaseQueue as CoreDatabaseQueue;

/**
 * PostgreSQL specific queue implementation.
 *
 * @ingroup queue
 */
class DatabaseQueue extends CoreDatabaseQueue {

  /**
   * Claims an item using a single atomic statement.
   *
   * Unlike the generic implementation, which selects a candidate item and
   * then races other consumers to update it, PostgreSQL can claim an item
   * with one UPDATE ... RETURNING statement. FOR UPDATE SKIP LOCKED makes
   * concurrent consumers skip items being claimed in parallel instead of
   * waiting for them, so a claim never blocks and never needs a retry.
   */
  public function claimItem($lease_time = 30) {
    try {
      $item = $this->connection->query(
        'UPDATE {' . static::TABLE_NAME . '} SET [expire] = :expire WHERE [item_id] = (SELECT [item_id] FROM {' . static::TABLE_NAME . '} WHERE [expire] = 0 AND [name] = :name ORDER BY [created], [item_id] ASC LIMIT 1 FOR UPDATE SKIP LOCKED) RETURNING [data], [created], [item_id]',
        [
          // We cannot rely on \Drupal::time()->getRequestTime() because items
          // might be claimed by a single consumer which runs longer than 1
          // second. If we continue to use ::getRequestTime() instead of
          // ::getCurrentTime(), we steal time from the lease, and will tend
          // to reset items before the lease should really expire.
          ':expire' => \Drupal::time()->getCurrentTime() + $lease_time,
          ':name' => $this->name,
        ],
      )->fetchObject();
    }
    catch (\Exception $e) {
      $this->catchException($e);
      // If the table does not exist there are no items currently available
      // to claim.
      return FALSE;
    }
    if ($item) {
      $item->data = unserialize($item->data);
      return $item;
    }
    return FALSE;
  }

}
