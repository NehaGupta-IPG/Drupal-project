<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for treegrid_reorder.
 */
#[Group('treegrid_reorder')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
