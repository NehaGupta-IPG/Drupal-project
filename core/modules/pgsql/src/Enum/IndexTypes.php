<?php

declare(strict_types=1);

namespace Drupal\pgsql\Enum;

/**
 * Enum of supported index types.
 */
enum IndexTypes: string {

  case GIN = 'GIN';

  case GIST = 'GIST';

}
