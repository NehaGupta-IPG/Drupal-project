<?php

namespace Drupal\Core\Session;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;

/**
 * Manages user sessions.
 *
 * This class implements the custom session management code inherited from
 * Drupal 7 on top of the corresponding Symfony component. Regrettably the name
 * NativeSessionStorage is not quite accurate. In fact the responsibility for
 * storing and retrieving session data has been extracted from it in Symfony 2.1
 * but the class name was not changed.
 *
 * @todo In fact the NativeSessionStorage class already implements all of the
 *   functionality required by a typical Symfony application. Normally it is not
 *   necessary to subclass it at all. In order to reach the point where Drupal
 *   can use the Symfony session management unmodified, the code implemented
 *   here needs to be extracted either into a dedicated session handler proxy
 *   (e.g. sid-hashing) or relocated to the authentication subsystem.
 */
class SessionManager extends NativeSessionStorage implements SessionManagerInterface {

  use DependencySerializationTrait;

  /**
   * Constructs a new session manager instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy|\SessionHandlerInterface|null $handler
   *   The object to register as a PHP session handler.
   * @param \Drupal\Core\Session\MetadataBag $metadata_bag
   *   The session metadata bag.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
   *   The session configuration interface.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   *
   * @see \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage::setSaveHandler()
   */
  public function __construct(
    protected RequestStack $requestStack,
    AbstractProxy|\SessionHandlerInterface|null $handler,
    MetadataBag $metadata_bag,
    protected SessionConfigurationInterface $sessionConfiguration,
    protected TimeInterface $time,
  ) {
    parent::__construct([], $handler, $metadata_bag);
  }

  /**
   * {@inheritdoc}
   */
  public function start(): bool {
    $request = $this->requestStack->getCurrentRequest();
    $this->setOptions($this->sessionConfiguration->getOptions($request));

    if ($this->isCli()) {
      return FALSE;
    }

    return parent::start();
  }

  /**
   * {@inheritdoc}
   */
  public function save(): void {
    if ($this->isCli()) {
      // We don't have anything to do if we are not allowed to save the session.
      return;
    }

    // Write the session data.
    parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public function regenerate($destroy = FALSE, $lifetime = NULL): bool {
    // Nothing to do if we are not allowed to change the session.
    if ($this->isCli()) {
      return FALSE;
    }

    // Drupal will always destroy the existing session when regenerating a
    // session. This is inline with the recommendations of @link https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#renew-the-session-id-after-any-privilege-level-change
    // OWASP session management cheat sheet. @endlink
    $destroy = TRUE;

    return parent::regenerate($destroy, $lifetime);
  }

  /**
   * {@inheritdoc}
   */
  public function destroy() {
    if ($this->isCli()) {
      return;
    }

    session_destroy();

    // Unset the session cookies.
    $session_name = $this->getName();
    $cookies = $this->requestStack->getCurrentRequest()->cookies;
    // setcookie() can only be called when headers are not yet sent.
    if ($cookies->has($session_name) && !headers_sent()) {
      $params = session_get_cookie_params();
      setcookie($session_name, '', $this->time->getRequestTime() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
      $cookies->remove($session_name);
    }
  }

  /**
   * Returns whether the current PHP process runs on CLI.
   *
   * Command line clients do not support cookies nor sessions.
   *
   * @return bool
   *   TRUE if the current PHP process runs on CLI, otherwise FALSE>
   */
  protected function isCli() {
    return PHP_SAPI === 'cli';
  }

}
