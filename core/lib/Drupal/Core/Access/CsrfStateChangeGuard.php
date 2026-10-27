<?php

declare(strict_types=1);

namespace Drupal\Core\Access;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Deprecates state changes made by unprotected safe requests.
 *
 * @internal
 */
class CsrfStateChangeGuard {

  public function __construct(protected RequestStack $requestStack) {}

  /**
   * Deprecates writes during unprotected safe requests.
   */
  public function check(): void {
    $request = $this->requestStack->getMainRequest();
    if (!$request instanceof Request || !$request->attributes->get('_csrf_state_change_guard')) {
      return;
    }

    @trigger_error('Performing configuration and entity writes during an unprotected safe request is deprecated in drupal:12.0.0 and will throw a LogicException in drupal:13.0.0. Add a _csrf_token route requirement. See https://www.drupal.org/project/drupal/issues/3508087', E_USER_DEPRECATED);
  }

}
