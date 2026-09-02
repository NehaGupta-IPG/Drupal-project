<?php

declare(strict_types=1);

namespace Drupal\pgsql\Schema;

/**
 * Enum of supported index types.
 *
 * @see https://www.postgresql.org/docs/current/textsearch-indexes.html
 */
enum IndexType: string {

  case Gin = 'GIN';

  case Gist = 'GIST';

}
