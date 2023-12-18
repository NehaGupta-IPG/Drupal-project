<?php

declare(strict_types=1);

namespace Drupal\pgsql\Schema;

/**
 * Enum of supported index types.
 */
enum IndexType: string {

  case GIN = 'GIN';

  case GIST = 'GIST';

}
