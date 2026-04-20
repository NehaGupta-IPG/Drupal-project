<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\NullContainerStorage;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the NullContainerStorage.
 */
#[CoversClass(NullContainerStorage::class)]
#[Group('DependencyInjection')]
class NullContainerStorageTest extends UnitTestCase {

  /**
   * Tests that load() always returns NULL.
   */
  public function testLoadReturnsNull(): void {
    $storage = new NullContainerStorage();
    $kernel = $this->createStub(DrupalKernelInterface::class);
    $this->assertNull($storage->load('any_suffix', $kernel));
    $this->assertNull($storage->load('another_suffix', $kernel));
  }

  /**
   * Tests that dump() returns the ContainerBuilder as-is.
   */
  public function testDumpReturnsContainerBuilder(): void {
    $storage = new NullContainerStorage();
    $container = new ContainerBuilder();
    $kernel = $this->createStub(DrupalKernelInterface::class);
    $result = $storage->dump($container, 'any_suffix', $kernel);
    $this->assertSame($container, $result);
  }

  /**
   * Tests that invalidate() completes without error.
   */
  public function testInvalidateIsNoop(): void {
    $storage = new NullContainerStorage();
    $storage->invalidate();
    // No exception means success.
    $this->addToAssertionCount(1);
  }

}
