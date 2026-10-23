<?php

declare(strict_types=1);

namespace Drupal\Tests\mysqli\Kernel\mysqli;

use Drupal\Tests\mysql\Kernel\mysql\ConnectionUnitTest as BaseMySqlTest;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * MySQL-specific connection unit tests.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class ConnectionUnitTest extends BaseMySqlTest {

  /**
   * Tests pdo options override.
   */
  public function testConnectionOpen(): void {
    $this->markTestSkipped('mysqli is not a pdo driver.');
  }

}
