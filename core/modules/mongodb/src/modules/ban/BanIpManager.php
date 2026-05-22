<?php

namespace Drupal\mongodb\modules\ban;

use Drupal\ban\BanIpManager as CoreBanIpManager;

/**
 * The MongoDB implementation of \Drupal\ban\BanIpManager.
 */
class BanIpManager extends CoreBanIpManager {

  /**
   * {@inheritdoc}
   */
  public function isBanned($ip) {
    $prefixed_table = $this->connection->getPrefix() . 'ban_ip';
    return (bool) $this->connection->getConnection()->selectCollection($prefixed_table)->count(
      [
        'ip' => [
          '$eq' => (string) $ip,
        ],
      ],
      ['session' => $this->connection->getMongodbSession()],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function findAll() {
    return $this->connection->select('ban_ip', 'b')
      ->fields('b')
      ->execute()
      ->fetchAll();
  }

  /**
   * {@inheritdoc}
   */
  public function findById($ban_id) {
    return $this->connection->select('ban_ip', 'b')
      ->fields('b', ['ip'])
      ->condition('iid', (int) $ban_id)
      ->execute()
      ->fetchField();
  }

}
