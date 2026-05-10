<?php

declare(strict_types=1);

namespace Drupal\Core\Site;

/**
 * Defines possible maintenance mode types.
 */
enum MaintenanceType: string {

  /**
   * Site is being installed.
   */
  case Install = 'install';

  /**
   * Site is being updated.
   */
  case Update = 'update';

  /**
   * Site is in regular maintenance mode.
   */
  case Regular = 'regular';

}
