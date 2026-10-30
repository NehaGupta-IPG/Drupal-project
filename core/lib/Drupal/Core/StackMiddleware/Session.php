<?php

namespace Drupal\Core\StackMiddleware;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\ResponseKeepSessionOpenInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session as HttpFoundationSession;
use Symfony\Component\HttpFoundation\Session\SessionUtils;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Wrap session logic around a HTTP request.
 *
 * Note, the session service is wrapped in a closure in order to prevent
 * premature initialization of session storage (database).
 */
class Session implements HttpKernelInterface {

  /**
   * The wrapped HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * Constructs a Session stack middleware object.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The decorated kernel.
   * @param \Closure $sessionClosure
   *   A closure that wraps the session service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $sessionConfiguration
   *   The session configuration.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    #[AutowireServiceClosure('session')]
    protected \Closure $sessionClosure,
    protected readonly SessionConfigurationInterface $sessionConfiguration,
    protected readonly TimeInterface $time,
  ) {
    $this->httpKernel = $http_kernel;
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    // Initialize and start a session for web requests. Command line tools and
    // the parent site in functional tests must continue to use the ephemeral
    // session initialized and started in DrupalKernel::preHandle().
    if ($type === self::MAIN_REQUEST && PHP_SAPI !== 'cli') {
      $this->initializePersistentSession($request);
    }

    $result = $this->httpKernel->handle($request, $type, $catch);

    if ($type === self::MAIN_REQUEST && !$result instanceof ResponseKeepSessionOpenInterface && PHP_SAPI !== 'cli') {
      $request->getSession()->save();
      $this->synchronizeResponseCookie($request, $result);
    }

    return $result;
  }

  /**
   * Sets a session backed by persistent store and puts it on the request.
   *
   * Sessions for web requests need to be backed by a persistent session store
   * and a real session handler (responsible for session cookie management).
   * In contrast, a simple in-memory store is sufficient for command line tools
   * and tests. Hence, the persistent session should only ever be placed on web
   * requests while command line tools and the parent site in functional tests
   * must continue to use the ephemeral session initialized in
   * DrupalKernel::preHandle().
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @see \Drupal\Core\DrupalKernel::preHandle()
   */
  protected function initializePersistentSession(Request $request): void {
    /** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $session */
    $session = ($this->sessionClosure)();
    $session->start();
    $request->setSession($session);
  }

  /**
   * Synchronize PHP session related cookie response headers.
   *
   * The PHP session engine directly sets / removes session related cookie. This
   * is done by sending the necessary Set-Cookie response headers via the PHP
   * SAPI.
   *
   * To support sessions in persistent runtimes like e.g., roadrunner, the
   * session cookie needs to be present on the response object and must not be
   * sent by PHP itself.
   *
   * This method removes cookie related response headers sent by PHP and instead
   * ensures sets / clears the cookie on the response object if necessary.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response.
   *
   * @see https://github.com/php/php-src/blob/master/ext/session/session.c
   * @see https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/HttpKernel/EventListener/AbstractSessionListener.php
   */
  protected function synchronizeResponseCookie(Request $request, Response $response): void {
    $session = $request->getSession();
    $sessionName = $session->getName();
    $sessionId = $session->getId();
    $sessionOptions = $this->sessionConfiguration->getOptions($request);
    $sessionCookiePath = $sessionOptions['cookie_path'] ?? '/';
    $sessionCookieDomain = $sessionOptions['cookie_domain'] ?? NULL;
    $sessionCookieSecure = $sessionOptions['cookie_secure'] ?? FALSE;
    $sessionCookieHttpOnly = $sessionOptions['cookie_httponly'] ?? TRUE;
    $sessionCookieSameSite = $sessionOptions['cookie_samesite'] ?? Cookie::SAMESITE_LAX;

    SessionUtils::popSessionCookie($sessionName, $sessionId);

    $requestSessionCookieId = $request->cookies->get($sessionName);

    $isSessionEmpty = ($session instanceof HttpFoundationSession ? $session->isEmpty() : !$session->all()) && empty($_SESSION);
    if ($requestSessionCookieId && $isSessionEmpty) {
      // PHP internally sets the session cookie value to "deleted" when
      // setcookie() is called with empty string $value argument which happens
      // in \Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler::destroy
      // when the session gets invalidated (for example on logout) so we must
      // handle this case here too otherwise we would send two Set-Cookie
      // headers back with the response.
      SessionUtils::popSessionCookie($sessionName, 'deleted');
      $response->headers->clearCookie(
        $sessionName,
        $sessionCookiePath,
        $sessionCookieDomain,
        $sessionCookieSecure,
        $sessionCookieHttpOnly,
        $sessionCookieSameSite
      );
    }
    elseif ($sessionId !== $requestSessionCookieId && !$isSessionEmpty) {
      $expire = 0;
      $lifetime = $sessionOptions['cookie_lifetime'] ?? NULL;
      if ($lifetime) {
        $expire = $this->time->getRequestTime() + $lifetime;
      }

      $response->headers->setCookie(
        Cookie::create(
          $sessionName,
          $sessionId,
          $expire,
          $sessionCookiePath,
          $sessionCookieDomain,
          $sessionCookieSecure,
          $sessionCookieHttpOnly,
          FALSE,
          $sessionCookieSameSite
        )
      );
    }
  }

}
