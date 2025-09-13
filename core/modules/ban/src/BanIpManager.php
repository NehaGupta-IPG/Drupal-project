<?php

namespace Drupal\ban;

use Drupal\Core\Database\Connection;
use Drupal\ban\Event\BanEvent;

/**
 * Ban IP manager.
 */
class BanIpManager implements BanIpManagerInterface {

  /**
   * The database connection used to check the IP against.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a BanIpManager object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection which will be used to check the IP against.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function isBanned($ip) {
    return (bool) $this->connection->query("SELECT * FROM {ban_ip} WHERE [ip] = :ip", [':ip' => $ip])->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function findAll() {
    return $this->connection->query('SELECT * FROM {ban_ip}');
  }

  /**
   * {@inheritdoc}
   */
  public function banIp($ip) {
    // Allow subscribers (e.g. allowed list) to veto banning this IP.
    $event = new BanEvent($ip);
    /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatcher->dispatch($event, BanEvent::EVENT_NAME);

    // Exit early if a subscriber marked the IP as not to be banned.
    if (!$event->isBanned) {
      return;
    }

    // Persist the ban as in core.
    $this->connection->merge('ban_ip')
      ->key('ip', $ip)
      ->fields(['ip' => $ip])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function unbanIp($id) {
    $this->connection->delete('ban_ip')
      ->condition('ip', $id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function findById($ban_id) {
    return $this->connection->query("SELECT [ip] FROM {ban_ip} WHERE [iid] = :iid", [':iid' => $ban_id])->fetchField();
  }

}
