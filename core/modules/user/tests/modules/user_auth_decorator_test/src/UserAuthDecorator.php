<?php

declare(strict_types=1);

namespace Drupal\user_auth_decorator_test;

use Drupal\user\UserAuthInterface;

/**
 * Helper to validate UserAuthInterface BC layers are functional.
 */
class UserAuthDecorator implements UserAuthInterface {

  public function __construct(protected UserAuthInterface $inner) {
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate($username, #[\SensitiveParameter] $password) {
    return $this->inner->authenticate($username, $password);
  }

}
