<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Tests\system\Kernel\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for menu_tree_ui.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
