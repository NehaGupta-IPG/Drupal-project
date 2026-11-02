<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\TestTools\Attribute\ShareEnvironment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests that the container is shared between kernel test methods.
 */
#[CoversClass(KernelTestBase::class)]
#[Group('KernelTests')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class KernelTestSharedContainerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['container_rebuild_test'];

  /**
   * Whether register() adds a test parameter to the container.
   */
  protected static bool $addParameter = FALSE;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    if (static::$addParameter) {
      $container->setParameter('kernel_test_shared_container_test', TRUE);
    }
  }

  /**
   * Tests the number of container builds for each iteration of a test.
   */
  #[TestWith([1, 1])]
  #[TestWith([2, 1])]
  #[TestWith([3, 1])]
  public function testContainerRebuilds($iteration, $container_rebuilds): void {
    $this->assertSame($container_rebuilds, $this->container->get('state')->get('container_rebuild_test.count', 0), 'Container rebuilt once, iteration ' . $iteration);
  }

  /**
   * Rebuilds the container during a test method.
   *
   * @see ::testRebuiltContainerIsNotShared()
   */
  public function testContainerRebuildDuringMethod(): void {
    static::$addParameter = TRUE;
    $this->container->get('kernel')->rebuildContainer();
    $this->assertTrue(\Drupal::getContainer()->hasParameter('kernel_test_shared_container_test'));
  }

  /**
   * Tests that a container rebuilt in one method is not used by later methods.
   *
   * This must run after ::testContainerRebuildDuringMethod().
   */
  public function testRebuiltContainerIsNotShared(): void {
    $this->assertFalse($this->container->hasParameter('kernel_test_shared_container_test'));
  }

}
