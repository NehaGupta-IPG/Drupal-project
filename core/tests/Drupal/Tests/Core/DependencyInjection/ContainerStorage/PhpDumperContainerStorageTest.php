<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\DumpedContainer;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the PhpDumperContainerStorage.
 *
 * Tests run in separate processes because PhpDumper's FileLoader class_alias
 * can only be created once per process, and dump() generates unique container
 * class names that persist via class_exists().
 */
#[CoversClass(PhpDumperContainerStorage::class)]
#[Group('DependencyInjection')]
#[RunTestsInSeparateProcesses]
class PhpDumperContainerStorageTest extends UnitTestCase {

  /**
   * Creates a kernel stub that returns app root and site path.
   */
  protected function createKernelStub(): DrupalKernelInterface {
    $kernel = $this->createStub(DrupalKernelInterface::class);
    $kernel->method('getAppRoot')->willReturn('/app/root');
    $kernel->method('getSitePath')->willReturn('sites/default');
    return $kernel;
  }

  /**
   * Temporary files created during the test.
   *
   * @var string[]
   */
  protected array $tempFiles = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->tempFiles as $file) {
      @unlink($file);
    }
    parent::tearDown();
  }

  /**
   * Creates a mock PhpStorage that stores code in memory and includes via file.
   *
   * @param array $savedFiles
   *   Reference to an array that will collect saved filenames.
   * @param array $deletedFiles
   *   Reference to an array that will collect deleted filenames.
   *
   * @return \Drupal\Component\PhpStorage\PhpStorageInterface
   *   A stub PhpStorage.
   */
  protected function createPhpStorage(array &$savedFiles = [], array &$deletedFiles = []): PhpStorageInterface {
    $code = [];
    $phpStorage = $this->createStub(PhpStorageInterface::class);

    $phpStorage->method('save')
      ->willReturnCallback(function ($filename, $phpCode) use (&$code, &$savedFiles) {
        $code[$filename] = $phpCode;
        $savedFiles[] = $filename;
        return TRUE;
      });

    $phpStorage->method('load')
      ->willReturnCallback(function ($filename) use (&$code) {
        if (isset($code[$filename])) {
          $tempFile = tempnam(sys_get_temp_dir(), 'drupal_container_test_');
          $this->tempFiles[] = $tempFile;
          // Write the code to a temporary file so it can be included. There's
          // no way to define a named class from a string at runtime in PHP
          // without either eval() or including a file.
          file_put_contents($tempFile, $code[$filename]);
          include_once $tempFile;
        }
      });

    $phpStorage->method('delete')
      ->willReturnCallback(function ($filename) use (&$code, &$deletedFiles) {
        unset($code[$filename]);
        $deletedFiles[] = $filename;
        return TRUE;
      });

    $phpStorage->method('exists')
      ->willReturnCallback(function ($filename) use (&$code) {
        return isset($code[$filename]);
      });

    return $phpStorage;
  }

  /**
   * Tests that load() returns NULL when no cache sentinel exists.
   */
  public function testLoadReturnsNullWhenNoSentinel(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('container_hash:test_suffix')
      ->willReturn(FALSE);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), DumpedContainer::class);
    $this->assertNull($storage->load('test_suffix', $this->createKernelStub()));
  }

  /**
   * Tests that load() returns NULL when sentinel exists but file is missing.
   */
  public function testLoadReturnsNullWhenFileMissing(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $sentinel = (object) ['data' => 'abc123'];
    $cache->expects($this->once())
      ->method('get')
      ->with('container_hash:test_suffix')
      ->willReturn($sentinel);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), DumpedContainer::class);
    $this->assertNull($storage->load('test_suffix', $this->createKernelStub()));
  }

  /**
   * Tests that dump() saves the compiled container and returns an instance.
   */
  public function testDumpCreatesFileAndSetsSentinel(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $savedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles);

    $container = new ContainerBuilder();
    $container->compile();

    $cache->expects($this->once())
      ->method('set')
      ->willReturnCallback(function ($key, $hash) {
        $this->assertStringStartsWith('container_hash:', $key);
        $this->assertNotEmpty($hash);
      });

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, DumpedContainer::class);
    $result = $storage->dump($container, 'test_suffix', $this->createKernelStub());

    $this->assertInstanceOf(DumpedContainer::class, $result);
    $this->assertCount(1, $savedFiles);
    $this->assertStringStartsWith('DrupalContainer_', $savedFiles[0]);
    $this->assertStringEndsWith('.php', $savedFiles[0]);
  }

  /**
   * Tests that dump() handles cache exceptions gracefully (install scenario).
   */
  public function testDumpHandlesCacheException(): void {
    $cache = $this->createMock(CacheBackendInterface::class);

    $container = new ContainerBuilder();
    $container->compile();

    $cache->expects($this->once())
      ->method('set')
      ->willThrowException(new \Exception('Cache table does not exist'));

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), DumpedContainer::class);
    // Should not throw despite cache exception.
    $result = $storage->dump($container, 'test_suffix', $this->createKernelStub());
    $this->assertInstanceOf(DumpedContainer::class, $result);
  }

  /**
   * Tests that dump() with same hash skips file rewrite.
   */
  public function testDumpSameHashSkipsRewrite(): void {
    $cache = $this->createStub(CacheBackendInterface::class);
    $savedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles);

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, DumpedContainer::class);
    $result1 = $storage->dump($container, 'test_suffix', $this->createKernelStub());
    $result2 = $storage->dump($container, 'test_suffix', $this->createKernelStub());

    // Both should return a DumpedContainer of the same class.
    $this->assertInstanceOf(DumpedContainer::class, $result1);
    $this->assertSame($result1::class, $result2::class);
    // save() should only have been called once.
    $this->assertCount(1, $savedFiles);
  }

  /**
   * Tests that dump() deletes old file when hash changes.
   */
  public function testDumpDeletesOldFileOnRebuild(): void {
    $cache = $this->createStub(CacheBackendInterface::class);
    $savedFiles = [];
    $deletedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles, $deletedFiles);

    // First container.
    $container1 = new ContainerBuilder();
    $container1->setParameter('test_param', 'value1');
    $container1->compile();

    // Second container with different content.
    $container2 = new ContainerBuilder();
    $container2->setParameter('test_param', 'value2');
    $container2->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, DumpedContainer::class);
    $result1 = $storage->dump($container1, 'test_suffix', $this->createKernelStub());
    $result2 = $storage->dump($container2, 'test_suffix', $this->createKernelStub());

    // Different containers produce different classes.
    $this->assertNotSame($result1::class, $result2::class);
    // The old container file should have been deleted.
    $this->assertCount(1, $deletedFiles);
    $this->assertSame($savedFiles[0], $deletedFiles[0]);
  }

  /**
   * Tests that invalidate() calls deleteAll on the cache backend.
   */
  public function testInvalidateDeletesAllCacheEntries(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('deleteAll');

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), DumpedContainer::class);
    $storage->invalidate();
  }

}
