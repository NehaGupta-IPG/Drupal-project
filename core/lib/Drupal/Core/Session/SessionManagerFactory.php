<?php

declare(strict_types=1);

namespace Drupal\Core\Session;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Session manager factory service.
 */
class SessionManagerFactory implements SessionStorageFactoryInterface {

  /**
   * Constructs a new session manager factory instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
   *   The session configuration interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy|\SessionHandlerInterface|null $handler
   *   The object to register as a PHP session handler.
   * @param \Drupal\Core\Session\WriteSafeSessionHandler $writeSafeHandler
   *   The write safe session handler.
   *
   * @see \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage::setSaveHandler()
   */
  public function __construct(
    protected RequestStack $requestStack,
    protected Connection $connection,
    protected SessionConfigurationInterface $sessionConfiguration,
    protected TimeInterface $time,
    protected AbstractProxy|\SessionHandlerInterface|null $handler = NULL,
    protected WriteSafeSessionHandler $writeSafeHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function createStorage(?Request $request): SessionStorageInterface {
    $sessionManager = new SessionManager($this->requestStack, $this->connection, new MetadataBag(), $this->sessionConfiguration, $this->time);
    $sessionManager->setWriteSafeHandler($this->writeSafeHandler);
    return $sessionManager;
  }

}
