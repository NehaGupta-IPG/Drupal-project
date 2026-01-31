<?php

namespace Drupal\Core\Session;

use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Defines the session manager interface.
 */
interface SessionManagerInterface extends SessionStorageInterface {

  /**
   * Destroys the current session and removes session cookies.
   *
   * @deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Use
   * Symfony\Component\HttpFoundation\Session\Session::invalidate() instead.
   * @see https://www.drupal.org/node/XXXXXXX
   */
  public function destroy();

}
