<?php

declare(strict_types=1);

namespace Drupal\session_test;

use Drupal\Core\DestructableInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Writes to the session after the response has been sent.
 *
 * Destructable services run at the end of DrupalKernel::terminate(), when the
 * headers have already been flushed and the session has been closed.
 */
class SessionWriteOnDestruct implements DestructableInterface {

  public function __construct(protected RequestStack $requestStack) {}

  /**
   * Marks the service as used, so that destruct() is called.
   */
  public function register(): void {}

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    $this->requestStack->getCurrentRequest()->getSession()->set('session_test_destruct', 'written');
  }

}
