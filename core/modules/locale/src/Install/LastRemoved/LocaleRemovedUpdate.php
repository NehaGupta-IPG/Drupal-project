<?php

declare(strict_types=1);

namespace Drupal\locale\Install\LastRemoved;

use Drupal\Core\Extension\Install\LastRemovedInterface;

/**
 * Last update removed for Locale module.
 */
class LocaleRemovedUpdate implements LastRemovedInterface {

  /**
   * Implements hook_update_last_removed().
   */
  public function getLastRemoved(): int {
    return 10300;
  }

}
