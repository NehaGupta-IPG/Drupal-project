<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\Site\Settings;
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
   * Temporary files created during the test.
   *
   * @var string[]
   */
  protected array $tempFiles = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    new Settings([
      'container_base_class' => PhpContainer::class,
    ]);
  }

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
          $tempFile = tempnam(FileSystem::getOsTemporaryDirectory(), 'drupal_container_test_');
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
   * Tests that load() returns NULL when no hash exists in the cache.
   */
  public function testLoadReturnsNullWhenNoCache(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('container_hash:test_suffix')
      ->willReturn(FALSE);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_suffix'));
  }

  /**
   * Tests load() returns NULL when hash exists in cache but file is missing.
   */
  public function testLoadReturnsNullWhenFileMissing(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('container_hash:test_suffix')
      ->willReturn((object) ['data' => 'abc123']);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_suffix'));
  }

  /**
   * Tests that dump() saves the compiled container and returns an instance.
   */
  public function testDumpCreatesFileAndSetsHashInCache(): void {
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

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result = $storage->dump($container, 'test_suffix');

    $this->assertInstanceOf(PhpContainer::class, $result);
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

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    // Should not throw despite cache exception.
    $result = $storage->dump($container, 'test_suffix');
    $this->assertInstanceOf(PhpContainer::class, $result);
  }

  /**
   * Tests that dump() with same hash skips file and cache rewrites.
   */
  public function testDumpSameHashSkipsRewrites(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())->method('set');

    $savedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles);

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result1 = $storage->dump($container, 'test_suffix');
    $result2 = $storage->dump($container, 'test_suffix');

    // Both should return a PhpContainer of the same class.
    $this->assertInstanceOf(PhpContainer::class, $result1);
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

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result1 = $storage->dump($container1, 'test_suffix');
    $result2 = $storage->dump($container2, 'test_suffix');

    // Different containers produce different classes.
    $this->assertNotSame($result1::class, $result2::class);
    $storage->destruct();
    // The old container file should have been deleted.
    $this->assertCount(1, $deletedFiles);
    $this->assertSame($savedFiles[0], $deletedFiles[0]);
  }

  /**
   * Tests that cache hash is updated before old container file is deleted.
   *
   * This ordering is critical: if the old file is deleted before the cache
   * points to the new hash, a concurrent request could read the stale hash
   * from cache and fail to load the (now deleted) old container file.
   */
  public function testDumpUpdatesCacheBeforeDeletingOldFile(): void {
    $operations = [];
    $code = [];

    // Build a cache mock that records set() calls.
    $cache = $this->createStub(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $cache->method('set')
      ->willReturnCallback(function ($key, $hash) use (&$operations) {
        $operations[] = ['cache_set', $hash];
      });

    // Build a PhpStorage mock that records save/load/delete calls.
    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')
      ->willReturnCallback(function ($filename, $phpCode) use (&$code, &$operations) {
        $code[$filename] = $phpCode;
        $operations[] = ['save', $filename];
        return TRUE;
      });
    $phpStorage->method('load')
      ->willReturnCallback(function ($filename) use (&$code) {
        if (isset($code[$filename])) {
          $tempFile = tempnam(FileSystem::getOsTemporaryDirectory(), 'drupal_container_test_');
          $this->tempFiles[] = $tempFile;
          file_put_contents($tempFile, $code[$filename]);
          include_once $tempFile;
        }
      });
    $phpStorage->method('delete')
      ->willReturnCallback(function ($filename) use (&$code, &$operations) {
        unset($code[$filename]);
        $operations[] = ['delete', $filename];
        return TRUE;
      });
    $phpStorage->method('exists')
      ->willReturnCallback(function ($filename) use (&$code) {
        return isset($code[$filename]);
      });

    // First dump.
    $container1 = new ContainerBuilder();
    $container1->setParameter('test_param', 'value1');
    $container1->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $storage->dump($container1, 'test_suffix');

    // Reset operations log before the second dump.
    $operations = [];

    // Second dump with different content triggers delete of old file.
    $container2 = new ContainerBuilder();
    $container2->setParameter('test_param', 'value2');
    $container2->compile();

    $storage->dump($container2, 'test_suffix');
    $storage->destruct();

    // Extract operation types in order.
    $opTypes = array_column($operations, 0);

    // The cache_set (pointing to new hash) must come before the delete
    // (removing the old file).
    $cacheSetIndex = array_search('cache_set', $opTypes);
    $deleteIndex = array_search('delete', $opTypes);

    $this->assertNotFalse($cacheSetIndex, 'cache_set should have been called');
    $this->assertNotFalse($deleteIndex, 'delete should have been called');
    $this->assertLessThan($deleteIndex, $cacheSetIndex, 'Cache hash must be updated before old container file is deleted');
  }

  /**
   * Tests that dump() saves but skips load when class already exists in memory.
   */
  public function testDumpSavesButSkipsLoadWhenClassAlreadyExists(): void {
    $cache = $this->createStub(CacheBackendInterface::class);
    $savedFiles = [];
    $loadedFiles = [];
    $deletedFiles = [];
    $code = [];

    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')
      ->willReturnCallback(function ($filename, $phpCode) use (&$code, &$savedFiles) {
        $code[$filename] = $phpCode;
        $savedFiles[] = $filename;
        return TRUE;
      });
    $phpStorage->method('load')
      ->willReturnCallback(function ($filename) use (&$code, &$loadedFiles) {
        $loadedFiles[] = $filename;
        if (isset($code[$filename])) {
          $tempFile = tempnam(FileSystem::getOsTemporaryDirectory(), 'drupal_container_test_');
          $this->tempFiles[] = $tempFile;
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

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());

    // First dump: container A.
    $containerA = new ContainerBuilder();
    $containerA->setParameter('test_param', 'value_a');
    $containerA->compile();
    $resultA1 = $storage->dump($containerA, 'test_suffix');

    // Second dump: container B (different hash).
    $containerB = new ContainerBuilder();
    $containerB->setParameter('test_param', 'value_b');
    $containerB->compile();
    $resultB = $storage->dump($containerB, 'test_suffix');

    $this->assertNotSame($resultA1::class, $resultB::class);

    $this->assertCount(2, $savedFiles);
    $this->assertCount(2, $loadedFiles);
    $this->assertCount(0, $deletedFiles);

    // Reset tracking before the critical third dump.
    $savedFiles = [];
    $loadedFiles = [];
    $deletedFiles = [];

    // Third dump: container A again (same hash as first dump). The class
    // DrupalContainer_<hashA> still exists in memory from the first dump.
    $resultA2 = $storage->dump($containerA, 'test_suffix');
    $storage->destruct();

    // The returned container must be the same class as the first dump.
    $this->assertSame($resultA1::class, $resultA2::class);
    // The file has already been saved to disk so no need to re-save it.
    $this->assertCount(0, $savedFiles);
    // Also load() must NOT be called (class already exists, re-including would
    // cause "Cannot redeclare class").
    $this->assertCount(0, $loadedFiles);
    // The old container B file should have been deleted.
    $this->assertCount(1, $deletedFiles);
  }

  /**
   * Tests dump() elseif branch when the class already exists in memory.
   *
   * When a new PhpDumperContainerStorage instance encounters a container hash
   * whose class is already defined in memory (e.g. the kernel was booted,
   * terminated, and booted again), the behavior depends on whether the file
   * still exists on disk:
   *
   * - Class exists AND file exists: skip both save() and load() — no disk I/O.
   * - Class exists AND file missing: call save() to restore the file for future
   *   requests, but still skip load() to avoid a "Cannot redeclare class" fatal
   *   that MTimeProtectedFileStorage would trigger by including the class from a
   *   new mtime-based path.
   */
  public function testDumpElseBranchSkipsLoadWhenClassExists(): void {
    $cache = $this->createStub(CacheBackendInterface::class);
    $savedFiles = [];
    $loadedFiles = [];
    $code = [];

    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')
      ->willReturnCallback(function ($filename, $phpCode) use (&$code, &$savedFiles) {
        $code[$filename] = $phpCode;
        $savedFiles[] = $filename;
        return TRUE;
      });
    $phpStorage->method('load')
      ->willReturnCallback(function ($filename) use (&$code, &$loadedFiles) {
        $loadedFiles[] = $filename;
        if (isset($code[$filename])) {
          $tempFile = tempnam(FileSystem::getOsTemporaryDirectory(), 'drupal_container_test_');
          $this->tempFiles[] = $tempFile;
          file_put_contents($tempFile, $code[$filename]);
          include_once $tempFile;
        }
      });
    $phpStorage->method('exists')
      ->willReturnCallback(function ($filename) use (&$code) {
        return isset($code[$filename]);
      });

    $containerA = new ContainerBuilder();
    $containerA->setParameter('test_param', 'value_a');
    $containerA->compile();

    // First storage instance: dump A to define the class and write the file.
    $storage1 = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result1 = $storage1->dump($containerA, 'test_suffix');
    $this->assertCount(1, $savedFiles);
    $this->assertCount(1, $loadedFiles);

    // Reset tracking before second instance.
    $savedFiles = [];
    $loadedFiles = [];

    // Second storage instance: class A exists in memory AND file exists on disk.
    // Neither save() nor load() should be called — no disk I/O needed.
    $storage2 = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result2 = $storage2->dump($containerA, 'test_suffix');
    $this->assertSame($result1::class, $result2::class);
    $this->assertCount(0, $savedFiles, 'save() must be skipped when class and file both already exist');
    $this->assertCount(0, $loadedFiles, 'load() must be skipped when class already exists in memory');

    // Delete the file from storage to simulate destruct() having cleaned it up
    // (e.g. because the active container changed). The class is still in memory.
    $containerAFilename = $result1::class . '.php';
    unset($code[$containerAFilename]);

    // Reset tracking before third instance.
    $savedFiles = [];
    $loadedFiles = [];

    // Third storage instance: class A exists in memory BUT file no longer
    // exists on disk. save() MUST be called to restore the file for future
    // requests. load() must still be skipped.
    $storage3 = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result3 = $storage3->dump($containerA, 'test_suffix');
    $this->assertSame($result1::class, $result3::class);
    $this->assertCount(1, $savedFiles, 'save() must be called when the file is missing even if the class exists');
    $this->assertCount(0, $loadedFiles, 'load() must be skipped even when file had to be re-saved');
  }

  /**
   * Tests that invalidate() calls deleteAll on the cache backend.
   */
  public function testInvalidateDeletesAllCacheEntries(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('deleteAll');

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $storage->invalidate();
  }

  /**
   * Tests that dump() returns the original container when save() fails.
   *
   * Covers the case where a LockingPhpStorageInterface implementation (such as
   * FlockMTimeProtectedFileStorage) returns FALSE from save() because another
   * worker on the same web head is already writing the file, or a write error
   * occurs.
   */
  public function testDumpReturnsFallbackContainerWhenSaveFails(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    // cache->set() must not be called: the hash must not be published when the
    // file was not successfully written.
    $cache->expects($this->never())->method('set');

    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')->willReturn(FALSE);
    $phpStorage->method('exists')->willReturn(FALSE);
    // load() must not be called when save() failed.
    $phpStorage->method('load')->willReturnCallback(
      fn() => $this->fail('load() must not be called when save() returns FALSE'),
    );

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $result = $storage->dump($container, 'test_suffix');

    // The original ContainerBuilder must be returned so the current request
    // can continue with an uncached container.
    $this->assertSame($container, $result);
  }

}
