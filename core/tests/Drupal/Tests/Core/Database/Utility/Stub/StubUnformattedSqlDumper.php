<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Utility\Stub;

use Drupal\Core\Database\Utility\SqlDumper;

/**
 * A dumper that always takes the branch used when the formatter is missing.
 *
 * The SQL formatter is a development dependency, so it is installed while
 * tests run. This stub makes the production branch testable.
 */
class StubUnformattedSqlDumper extends SqlDumper {

  /**
   * {@inheritdoc}
   */
  protected function hasFormatter(): bool {
    return FALSE;
  }

}
