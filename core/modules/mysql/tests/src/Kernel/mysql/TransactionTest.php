<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\KernelTests\Core\Database\DriverSpecificTransactionTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests transaction for the MySQL driver.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class TransactionTest extends DriverSpecificTransactionTestBase {
}
