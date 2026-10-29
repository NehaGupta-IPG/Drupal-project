<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Cache\Fixtures\RecoveryDriver;

use Drupal\Core\Database\Query\Upsert;
use Drupal\sqlite\Driver\Database\sqlite\Connection as SqliteConnection;

/**
 * Real SQLite connection with bounded, deterministic fault injection.
 */
class Connection extends SqliteConnection {

  /**
   * The exception injected while opening a new client.
   */
  public static ?\Throwable $openFailure = NULL;

  /**
   * The exception injected on writes through a new connection.
   */
  public static ?\Throwable $retryFailure = NULL;

  /**
   * The exception injected on this connection.
   */
  public ?\Throwable $failure = NULL;

  /**
   * The upsert call that triggers the injected error.
   */
  public int $failAt = 1;

  /**
   * Whether to remove the actual client before injecting the error.
   */
  public bool $destroyOnFailure = FALSE;

  /**
   * The number of upserts requested on this connection.
   */
  public int $upsertCalls = 0;

  /**
   * {@inheritdoc}
   */
  public static function open(#[\SensitiveParameter] array &$connection_options = []): object {
    if (self::$openFailure) {
      throw self::$openFailure;
    }
    return parent::open($connection_options);
  }

  /**
   * {@inheritdoc}
   */
  public function upsert($table, array $options = []): Upsert {
    $this->upsertCalls++;
    if ($this->failure && $this->upsertCalls === $this->failAt) {
      if ($this->destroyOnFailure) {
        $this->__destruct();
      }
      throw $this->failure;
    }
    if (!$this->failure && self::$retryFailure) {
      throw self::$retryFailure;
    }
    return parent::upsert($table, $options);
  }

}
