<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql\Plugin\views;

use Drupal\Tests\views\Kernel\Plugin\CastedIntFieldJoinTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MySQL specific cast handling.
 */
#[Group('Database')]
#[Group('views')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class MySqlCastedIntFieldJoinTest extends CastedIntFieldJoinTestBase {

  /**
   * The db type that should be used for casting fields as integers.
   */
  protected string $castingType = 'UNSIGNED';

}
