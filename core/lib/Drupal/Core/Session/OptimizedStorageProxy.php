<?php

declare(strict_types=1);

namespace Drupal\Core\Session;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Implements a session handler proxy which blocks premature access to storage.
 *
 * Short circuits any read, write and destroy operation for newly created
 * session identifiers.
 */
class OptimizedStorageProxy extends SessionHandlerProxy implements \SessionHandlerInterface, \SessionIdInterface, \SessionUpdateTimestampHandlerInterface {

  /**
   * Newly created session id.
   */
  protected ?string $newSessionId = NULL;

  /**
   * {@inheritdoc}
   */
  public function open(string $savePath, string $sessionName): bool {
    $this->newSessionId = NULL;
    return parent::open($savePath, $sessionName);
  }

  /**
   * {@inheritdoc}
   */
  public function create_sid(): string {
    $this->newSessionId = session_create_id();
    return $this->newSessionId;
  }

  /**
   * {@inheritdoc}
   */
  public function read(#[\SensitiveParameter] string $sessionId): string|false {
    return $sessionId === $this->newSessionId ? '' : parent::read($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function write(#[\SensitiveParameter] string $sessionId, string $data): bool {
    if ($sessionId === $this->newSessionId && $data === '') {
      return TRUE;
    }
    $this->newSessionId = NULL;
    return parent::write($sessionId, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function destroy(#[\SensitiveParameter] string $sessionId): bool {
    return $sessionId === $this->newSessionId ? TRUE : parent::destroy($sessionId);
  }

  /**
   * {@inheritdoc}
   */
  public function close(): bool {
    $this->newSessionId = NULL;
    return parent::close();
  }

}
