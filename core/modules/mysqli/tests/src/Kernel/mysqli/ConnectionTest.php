<?php

declare(strict_types=1);

namespace Drupal\Tests\mysqli\Kernel\mysqli;

use Drupal\Tests\mysql\Kernel\mysql\ConnectionTest as BaseMySqlTest;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * MySQL-specific connection tests.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class ConnectionTest extends BaseMySqlTest {
}
