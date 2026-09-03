<?php

declare(strict_types=1);

namespace Drupal\Tests\mysqli\Kernel\mysqli;

use Drupal\Tests\mysql\Kernel\mysql\DatabaseExceptionDumpTest as BaseMySqlTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests dumping the statement of the failures MySQL reports by error code.
 *
 * The mysqli driver has no exception handler of its own beyond mapping the
 * client exception, so it has to normalise the same error codes as the mysql
 * driver does.
 *
 * @see \Drupal\mysqli\Driver\Database\mysqli\ExceptionHandler
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class DatabaseExceptionDumpTest extends BaseMySqlTest {
}
