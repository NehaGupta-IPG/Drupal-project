<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prevents state changes made by unprotected GET requests.
 *
 * @internal
 */
class CsrfStateChangeGuard {

  /**
   * Constructs a CSRF state change guard.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(protected RequestStack $requestStack) {}

  /**
   * Ensures that the current request can make a state change.
   */
  public function check(): void {
    $request = $this->requestStack->getMainRequest();
    if (!$request instanceof Request || !$request->attributes->get('_csrf_state_change_guard')) {
      return;
    }

    throw new \LogicException('Configuration and entity writes cannot be performed during an unprotected GET request.');
  }

}
