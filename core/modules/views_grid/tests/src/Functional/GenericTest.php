<?php

declare(strict_types=1);

namespace Drupal\Tests\views_grid\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for views_grid.
 */
#[Group('views_grid')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
