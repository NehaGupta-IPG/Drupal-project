<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Access\CsrfStateChangeGuard;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Deprecates entity writes during unprotected safe requests.
 */
final class CsrfStateChangeHooks {

  public function __construct(private readonly CsrfStateChangeGuard $guard) {}

  /**
   * Implements hook_entity_presave() and hook_entity_predelete().
   */
  #[Hook('entity_presave')]
  #[Hook('entity_predelete')]
  public function check(EntityInterface $entity): void {
    $this->guard->check();
  }

}
