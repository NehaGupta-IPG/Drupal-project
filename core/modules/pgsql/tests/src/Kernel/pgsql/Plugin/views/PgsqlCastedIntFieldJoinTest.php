<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql\Plugin\views;

use Drupal\Tests\views\Kernel\Plugin\CastedIntFieldJoinTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests PostgreSQL specific cast handling.
 */
#[Group('Database')]
#[Group('views')]
#[RunTestsInSeparateProcesses]
class PgsqlCastedIntFieldJoinTest extends CastedIntFieldJoinTestBase {

  /**
   * The db type that should be used for casting fields as integers.
   */
  protected string $castingType = 'BIGINT';

  /**
   * {@inheritdoc}
   */
  protected function castExpression(string $field): string {
    // PostgreSQL raises an error for a value that is not a number instead of
    // treating it as one, so the value is tested before it is cast.
    return "CASE WHEN pg_input_is_valid(CAST($field AS TEXT), 'bigint') THEN CAST($field AS $this->castingType) END";
  }

}
