<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\FileSystem\FileSystem;
use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
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

    // listAll() and delete() model MTimeProtectedFileStorage, which stores each
    // file in a containing directory named after the virtual file name with the
    // '.php' extension removed. Hence listAll() returns names without '.php',
    // and delete() is tolerant of names with or without it.
    $normalize = fn ($filename) => str_ends_with($filename, '.php') ? substr($filename, 0, -4) : $filename;

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

    $phpStorage->method('listAll')
      ->willReturnCallback(function () use (&$code, $normalize) {
        return array_values(array_unique(array_map($normalize, array_keys($code))));
      });

    $phpStorage->method('delete')
      ->willReturnCallback(function ($filename) use (&$code, &$deletedFiles, $normalize) {
        $target = $normalize($filename);
        foreach (array_keys($code) as $stored) {
          if ($normalize($stored) === $target) {
            unset($code[$stored]);
          }
        }
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
      ->with('container_hash:test_identifier')
      ->willReturn(FALSE);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_identifier'));
  }

  /**
   * Tests load() returns NULL when hash exists in cache but file is missing.
   */
  public function testLoadReturnsNullWhenFileMissing(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('container_hash:test_identifier')
      ->willReturn((object) ['data' => 'abc123']);

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_identifier'));
  }

  /**
   * Tests that dump() saves the compiled container and sets the hash in cache.
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
    $storage->dump($container, 'test_identifier');

    // The compiled container and its confirmation marker are both saved.
    $this->assertCount(2, $savedFiles);
    $this->assertStringStartsWith('DrupalContainer_', $savedFiles[0]);
    $this->assertStringEndsWith('.php', $savedFiles[0]);
    $this->assertSame(basename($savedFiles[0], '.php') . '.confirmed', $savedFiles[1]);
  }

  /**
   * Tests that dump() propagates cache exceptions (e.g. a broken database).
   */
  public function testDumpPropagatesCacheException(): void {
    $cache = $this->createMock(CacheBackendInterface::class);

    $container = new ContainerBuilder();
    $container->compile();

    $cache->expects($this->once())
      ->method('set')
      ->willThrowException(new \Exception('An error occurred while writing to the cache'));

    $storage = new PhpDumperContainerStorage($cache, $this->createPhpStorage(), Settings::getInstance());
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('An error occurred while writing to the cache');
    $storage->dump($container, 'test_identifier');
  }

  /**
   * Tests that dump() with the same hash does not rewrite existing files.
   */
  public function testDumpSameHashSkipsFileRewrites(): void {
    $cache = new MemoryBackend($this->createStub(TimeInterface::class));
    $savedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles);

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $storage->dump($container, 'test_identifier');
    // The compiled container and its confirmation marker are both saved.
    $this->assertCount(2, $savedFiles);

    $this->assertInstanceOf(PhpContainer::class, $storage->load('test_identifier'));

    // Dumping the same container again produces the same hash and therefore
    // the same filenames. Both files already exist, so neither the container
    // nor its confirmation marker is rewritten.
    $storage->dump($container, 'test_identifier');
    $this->assertCount(2, $savedFiles);
    $this->assertInstanceOf(PhpContainer::class, $storage->load('test_identifier'));
  }

  /**
   * Tests that garbageCollection() removes all containers except the current.
   */
  public function testGarbageCollectionRemovesSupersededContainers(): void {
    $cache = new MemoryBackend($this->createStub(TimeInterface::class));
    $savedFiles = [];
    $deletedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles, $deletedFiles);

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());

    // A container left behind by a previous identifier (e.g. an earlier
    // deployment).
    $old = new ContainerBuilder();
    $old->setParameter('test_param', 'old');
    $old->compile();
    $storage->dump($old, 'old_identifier');

    // Two versions under the current identifier; the first is superseded by the
    // second. The storage is invalidated in between, as happens on a cache
    // rebuild, which must not stop the superseded container being removed.
    $superseded = new ContainerBuilder();
    $superseded->setParameter('test_param', 'superseded');
    $superseded->compile();
    $storage->dump($superseded, 'current_identifier');
    $storage->invalidate();

    $current = new ContainerBuilder();
    $current->setParameter('test_param', 'current');
    $current->compile();
    $storage->dump($current, 'current_identifier');

    // Each dump() saves the compiled container and its confirmation marker.
    $this->assertCount(6, $savedFiles);
    $this->assertCount(0, $deletedFiles);

    // The compiled class names are derived from the saved filenames, in dump
    // order: old (container, marker), superseded (container, marker),
    // current (container, marker).
    $oldClass = basename($savedFiles[0], '.php');
    $supersededClass = basename($savedFiles[2], '.php');
    $currentClass = basename($savedFiles[4], '.php');

    $storage->garbageCollection('current_identifier');

    // Only the current container is kept; the superseded and old containers
    // (and their markers) are removed.
    $this->assertContains($oldClass, $deletedFiles);
    $this->assertContains($oldClass . '.confirmed', $deletedFiles);
    $this->assertContains($supersededClass, $deletedFiles);
    $this->assertContains($supersededClass . '.confirmed', $deletedFiles);
    $this->assertNotContains($currentClass, $deletedFiles);
    $this->assertNotContains($currentClass . '.confirmed', $deletedFiles);
    $this->assertCount(4, $deletedFiles);
  }

  /**
   * Tests that garbageCollection() keeps an unconfirmed container.
   */
  public function testGarbageCollectionKeepsUnconfirmedContainer(): void {
    $cache = new MemoryBackend($this->createStub(TimeInterface::class));
    $savedFiles = [];
    $deletedFiles = [];
    $phpStorage = $this->createPhpStorage($savedFiles, $deletedFiles);

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());

    // A genuinely old, fully confirmed container that should be removed.
    $old = new ContainerBuilder();
    $old->setParameter('test_param', 'old');
    $old->compile();
    $storage->dump($old, 'old_identifier');
    $oldClass = basename($savedFiles[0], '.php');

    // The container the cache currently points to for 'current_identifier'.
    $current = new ContainerBuilder();
    $current->setParameter('test_param', 'current');
    $current->compile();
    $storage->dump($current, 'current_identifier');

    // Simulate a concurrent dump() for the same identifier that has written its
    // file but has not yet published its hash to the cache or written its
    // confirmation marker.
    $phpStorage->save('DrupalContainer_unconfirmed.php', '<?php class DrupalContainer_unconfirmed {}');

    $storage->garbageCollection('current_identifier');

    // The old, confirmed container (and its marker) are removed; the
    // unconfirmed container survives this run rather than being deleted
    // outright.
    $this->assertContains($oldClass, $deletedFiles);
    $this->assertContains($oldClass . '.confirmed', $deletedFiles);
    $this->assertNotContains('DrupalContainer_unconfirmed', $deletedFiles);
    $this->assertCount(2, $deletedFiles);
  }

  /**
   * Tests that load() skips including the file when the class is in memory.
   */
  public function testLoadSkipsIncludeWhenClassInMemory(): void {
    $cache = new MemoryBackend($this->createStub(TimeInterface::class));
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

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());

    // dump() writes the file (and its confirmation marker) but must not
    // include it.
    $storage->dump($container, 'test_identifier');
    $this->assertCount(2, $savedFiles);
    $this->assertCount(0, $loadedFiles, 'dump() must not include the compiled container');

    // The first load() includes the file to bring the class into memory.
    $first = $storage->load('test_identifier');
    $this->assertInstanceOf(PhpContainer::class, $first);
    $this->assertCount(1, $loadedFiles);

    // The second load() must not include the file again: the class is already
    // in memory, and re-including would cause "Cannot redeclare class".
    $second = $storage->load('test_identifier');
    $this->assertInstanceOf(PhpContainer::class, $second);
    $this->assertCount(1, $loadedFiles, 'load() must not re-include when the class already exists in memory');
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
   * Tests that dump() does not publish the hash when save() fails.
   */
  public function testDumpDoesNotPublishHashWhenSaveFails(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    // cache->set() must not be called: the hash must not be published when the
    // file was not successfully written.
    $cache->expects($this->never())->method('set');

    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')->willReturn(FALSE);
    $phpStorage->method('exists')->willReturn(FALSE);

    $container = new ContainerBuilder();
    $container->compile();

    $storage = new PhpDumperContainerStorage($cache, $phpStorage, Settings::getInstance());
    $storage->dump($container, 'test_identifier');
  }

}
