<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation_layout\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for navigation_layout.
 */
#[Group('navigation_layout')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
