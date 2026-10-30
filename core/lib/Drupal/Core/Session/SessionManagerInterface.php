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
   * @deprecated in drupal:11.5.0 and is removed from drupal:14.0.0. Use
   * Symfony\Component\HttpFoundation\Session\Session::invalidate() instead.
   * @see https://www.drupal.org/node/3570844
   */
  public function destroy();

}
