<?php

declare(strict_types=1);

namespace Drupal\user\Install\LastRemoved;

use Drupal\Core\Extension\Install\LastRemovedInterface;

/**
 * Field and Field UI hook implementations for User module.
 */
class UserRemovedUpdate implements LastRemovedInterface {

  /**
   * Implements hook_update_last_removed().
   */
  public function getLastRemoved(): int {
    return 10000;
  }

}
