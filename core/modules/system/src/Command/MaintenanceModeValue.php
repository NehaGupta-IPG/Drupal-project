<?php

declare(strict_types=1);

namespace Drupal\system\Command;

/**
 * Enumeration of values accepted by the maint:set command.
 */
enum MaintenanceModeValue: string {

  case On = '1';
  case Off = '0';

}
