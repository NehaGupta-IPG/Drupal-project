<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Kernel;

use Drupal\Tests\system\Kernel\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for treegrid_reorder.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
