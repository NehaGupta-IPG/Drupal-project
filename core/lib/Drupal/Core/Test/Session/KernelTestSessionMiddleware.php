<?php

namespace Drupal\Core\Test\Session;

use Drupal\Core\Session\ResponseKeepSessionOpenInterface;
use Drupal\Core\StackMiddleware\Session;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replacement for the session middleware in Kernel tests that need sessions.
 *
 * This has the following differences:
 *  - The check for whether to start a session does not check for PHP being run
 *    on the CLI.
 *  - The session cookie is set manually into the response headers, as PHP does
 *    not send headers on the CLI.
 *
 * @see \Drupal\Tests\HttpKernelUiHelperTrait::alterSessionServices()
 */
class KernelTestSessionMiddleware extends Session {

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response {
    // Initialize and start a session.
    if ($type === self::MAIN_REQUEST) {
      $this->initializePersistentSession($request);
    }

    $result = $this->httpKernel->handle($request, $type, $catch);

    if ($type === self::MAIN_REQUEST && !$result instanceof ResponseKeepSessionOpenInterface) {
      $request->getSession()->save();

      // We need to set the session cookie on the response header ourselves, as
      // PHP will not do it because it sees we are on the CLI. Set a basic
      // cookie value with the session name and ID.
      $session_name = $request->getSession()->getName();
      $session_id = session_id();

      $result->headers->setCookie(Cookie::create($session_name, $session_id));
    }

    return $result;
  }

}
