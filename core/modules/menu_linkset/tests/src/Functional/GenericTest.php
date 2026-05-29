<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_linkset\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for menu_linkset.
 */
#[Group('menu_linkset')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
