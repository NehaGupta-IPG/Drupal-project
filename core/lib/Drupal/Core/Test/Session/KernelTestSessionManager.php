<?php

namespace Drupal\Core\Test\Session;

use Drupal\Core\Session\SessionManager;

/**
 * Replacement for the session manager in Kernel tests that need sessions.
 *
 * @see \Drupal\Tests\HttpKernelUiHelperTrait::alterSessionServices()
 */
class KernelTestSessionManager extends SessionManager {

  /**
   * {@inheritdoc}
   */
  protected function isCli() {
    // Override so that checks for CLI return FALSE and sessions are started.
    return FALSE;
  }

}
