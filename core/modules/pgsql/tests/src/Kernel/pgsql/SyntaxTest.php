<?php

declare(strict_types=1);

namespace Drupal\Tests\pgsql\Kernel\pgsql;

use Drupal\KernelTests\Core\Database\DriverSpecificSyntaxTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests PostgreSQL syntax interpretation.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class SyntaxTest extends DriverSpecificSyntaxTestBase {
}
