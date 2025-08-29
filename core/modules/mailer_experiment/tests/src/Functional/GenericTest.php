<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer_experiment\Functional;

use Drupal\Tests\system\Functional\Module\GenericModuleTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Generic module test for mailer_experiment.
 */
#[Group('mailer_experiment')]
#[RunTestsInSeparateProcesses]
class GenericTest extends GenericModuleTestBase {}
