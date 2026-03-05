<?php

declare(strict_types=1);

namespace Drupal\pgsql\Schema;

/**
 * Enum of supported index types.
 */
enum IndexType: string {

  case Gin = 'GIN';

  case Gist = 'GIST';

}
