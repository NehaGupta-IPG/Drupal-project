<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\Tests\Core\Foo;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\BrowserTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[ShareEnvironment]
abstract class AbstractSharingTest extends KernelTestBase {
}

#[Group('Test')]
#[RunTestsInSeparateProcesses]
class MissingShareEnvironment extends KernelTestBase {
}

#[Group('Test')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class SharesEnvironment extends KernelTestBase {
}

#[Group('Test')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class DoesNotShareEnvironment extends KernelTestBase {
}

#[Group('Test')]
#[RunTestsInSeparateProcesses]
class NotAKernelTest extends BrowserTestBase {
}

#[ShareEnvironment(FALSE)]
abstract class AbstractNotSharingTest extends KernelTestBase {
}
