<?php

declare(strict_types=1);

namespace Drupal\Tests\workspaces\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests access on workspaces.
 */
#[Group('workspaces')]
#[RunTestsInSeparateProcesses]
final class WorkspaceBundleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'workspaces',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('workspace');
  }

  /**
   * Test workspace bundle key.
   */
  public function testWorkspaceBundleKey(): void {
    $workspaceType = \Drupal::entityTypeManager()->getDefinition('workspace');
    $this->assertSame('provider', $workspaceType->getKey('bundle'));
  }

}
