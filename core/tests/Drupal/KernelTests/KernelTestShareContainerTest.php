<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\TestTools\Attribute\ShareContainer;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests that kernel tests share their compiled container.
 */
#[CoversClass(KernelTestBase::class)]
#[Group('KernelTests')]
#[RunTestsInSeparateProcesses]
#[ShareContainer]
class KernelTestShareContainerTest extends KernelTestBase {

  /**
   * The directory for shared containers before this test class ran.
   */
  protected static string|false $previousContainerDirectory = FALSE;

  /**
   * The directory for shared containers.
   */
  protected static function getContainerDirectory(): string {
    // The variable holds the ID of the test runner process.
    return FileSystem::getOsTemporaryDirectory() . '/drupal-test-share-container-' . getenv('DRUPAL_TEST_SHARE_CONTAINER_TEST');
  }

  /**
   * Uses a directory of shared containers of its own.
   */
  #[BeforeClass]
  public static function setContainerDirectory(): void {
    // PHPUnit runs this once in the test runner process, and again in every
    // test method process, which inherits the environment.
    if (getenv('DRUPAL_TEST_SHARE_CONTAINER_TEST') === FALSE) {
      static::$previousContainerDirectory = getenv('DRUPAL_TEST_CONTAINER_DIRECTORY');
      putenv('DRUPAL_TEST_SHARE_CONTAINER_TEST=' . getmypid());
      putenv('DRUPAL_TEST_CONTAINER_DIRECTORY=' . static::getContainerDirectory());
      mkdir(static::getContainerDirectory());
    }
  }

  /**
   * Removes the directory of shared containers.
   */
  #[AfterClass]
  public static function removeContainerDirectory(): void {
    // Only the test runner process removes the directory.
    if (getenv('DRUPAL_TEST_SHARE_CONTAINER_TEST') === (string) getmypid()) {
      array_map('unlink', glob(static::getContainerDirectory() . '/*'));
      rmdir(static::getContainerDirectory());
      putenv('DRUPAL_TEST_SHARE_CONTAINER_TEST');
      putenv('DRUPAL_TEST_CONTAINER_DIRECTORY' . (static::$previousContainerDirectory === FALSE ? '' : '=' . static::$previousContainerDirectory));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Record that the container was compiled.
    file_put_contents(static::getContainerDirectory() . '/compiled.txt', '1', FILE_APPEND);
  }

  /**
   * Tests that containers are only compiled by the first test method.
   */
  #[TestWith([1])]
  #[TestWith([2])]
  public function testContainerIsShared(int $iteration): void {
    $this->assertInstanceOf(PhpContainer::class, $this->container);
    $this->assertSame($this, $this->container->get(KernelTestBase::class));

    // The container for the new module list is shared as well.
    $this->enableModules(['user']);
    $this->assertInstanceOf(PhpContainer::class, $this->container);
    $this->assertSame($this, $this->container->get(KernelTestBase::class));
    $this->assertTrue($this->container->get('module_handler')->moduleExists('user'));

    // The first test method compiled the container the kernel booted with,
    // and the container for the new module list.
    $this->assertSame('11', file_get_contents(static::getContainerDirectory() . '/compiled.txt'), "Containers compiled once, iteration $iteration");
  }

}
