<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\KernelTests\Core\Database\DriverSpecificSyntaxTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MySql syntax interpretation.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class SyntaxTest extends DriverSpecificSyntaxTestBase {

  /**
   * Test that SQL injection against emulated prepares is not possible.
   */
  public function testSecurePrepares(): void {
    // This injection is only possible with emulated prepares.
    $this->expectException(DatabaseExceptionWrapper::class);
    $col = urldecode('\?%23%00');
    $value = "x` FROM (SELECT data AS `'x` from " . $this->connection->getPrefix() . "config)y;#";
    $this->connection->query("SELECT `$col` FROM {test} where name = ?", [$value]);
  }

}
