<?php

declare(strict_types=1);

namespace Drupal\pgsql\Session;

use Drupal\Core\Session\SessionHandler as CoreSessionHandler;
use Drupal\pgsql\TableVerifyTrait;

/**
 * PostgreSQL implementation of the database session handler.
 *
 * @see \Drupal\pgsql\TableVerifyTrait
 */
class SessionHandler extends CoreSessionHandler {

  use TableVerifyTrait;

  /**
   * {@inheritdoc}
   */
  public function read(#[\SensitiveParameter] string $sessionId): string {
    $this->verifyTableInTransaction('sessions');
    return parent::read($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function write(#[\SensitiveParameter] string $sessionId, string $data): bool {
    $this->verifyTableInTransaction('sessions');
    return parent::write($sessionId, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function destroy(#[\SensitiveParameter] string $sessionId): bool {
    $this->verifyTableInTransaction('sessions');
    return parent::destroy($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function gc(int $lifetime): int|false {
    $this->verifyTableInTransaction('sessions');
    return parent::gc($lifetime);
  }

}
