<?php

namespace Drupal\user;

/**
 * An interface for validating user authentication credentials.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
 *   UserAuthenticationInterface instead.
 * @see https://www.drupal.org/project/drupal/issues/3427298
 */
interface UserAuthInterface {

  /**
   * Validates user authentication credentials.
   *
   * @param string $username
   *   The user name to authenticate.
   * @param string $password
   *   A plain-text password, such as trimmed text from form values.
   *
   * @return int|bool
   *   The user's uid on success, or FALSE on failure to authenticate.
   */
  public function authenticate($username, #[\SensitiveParameter] $password);

}
