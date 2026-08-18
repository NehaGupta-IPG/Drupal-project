<?php

declare(strict_types=1);

namespace Drupal\Tests\mysqli\Kernel\mysqli;

use Drupal\Tests\mysql\Kernel\mysql\ConnectionUnitTest as BaseMySqlTest;
use Drupal\TestTools\Attribute\Skip;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * MySQL-specific connection unit tests.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ConnectionUnitTest extends BaseMySqlTest {

  /**
   * Tests pdo options override.
   */
  #[Skip('Permanent: see https://www.drupal.org/node/3259709')]
  public function testConnectionOpen(): void {
  }

}
