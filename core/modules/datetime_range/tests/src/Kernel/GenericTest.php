<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime_range\Kernel;

use Drupal\Tests\system\Kernel\Module\GenericModuleTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for datetime_range.
 */
#[Group('datetime_range')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment(FALSE)]
class GenericTest extends GenericModuleTestBase {}
