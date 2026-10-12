<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DependencyInjection\ContainerStorage;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\PhpStorage\FileStorage;
use Drupal\Component\PhpStorage\MTimeProtectedFileStorage;
use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ContainerStorage\PhpDumperContainerStorage;
use Drupal\Core\DependencyInjection\PhpContainer;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests the PhpDumperContainerStorage.
 */
#[CoversClass(PhpDumperContainerStorage::class)]
#[Group('DependencyInjection')]
class PhpDumperContainerStorageTest extends UnitTestCase {

  /**
   * The cache backend.
   */
  protected MemoryBackend $cache;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    new Settings(['container_base_class' => PhpContainer::class]);
    $this->cache = new MemoryBackend($this->createStub(TimeInterface::class));
    vfsStream::setup('php');
  }

  /**
   * Creates a PHP storage in the virtual file system.
   *
   * @param string $class
   *   The PhpStorageInterface implementation to use.
   *
   * @return \Drupal\Component\PhpStorage\PhpStorageInterface
   *   The PHP storage.
   */
  protected function createPhpStorage(string $class = MTimeProtectedFileStorage::class): PhpStorageInterface {
    return new $class([
      'directory' => vfsStream::url('php'),
      'bin' => 'service_container',
      'secret' => 'secret',
    ]);
  }

  /**
   * Creates a compiled container builder.
   *
   * Each container has a random parameter value, so its compiled class name is
   * unique and cannot clash with a class loaded by another test.
   *
   * @return \Drupal\Core\DependencyInjection\ContainerBuilder
   *   The compiled container builder.
   */
  protected function createContainerBuilder(): ContainerBuilder {
    $container = new ContainerBuilder();
    $container->setParameter('test_param', $this->randomMachineName());
    $container->compile();
    return $container;
  }

  /**
   * Returns the class name of the container a dump() call compiled.
   *
   * @param string $identifier
   *   The container identifier.
   *
   * @return string
   *   The container class name.
   */
  protected function getContainerClass(string $identifier): string {
    return 'DrupalContainer_' . $this->cache->get('container_hash:' . $identifier)->data;
  }

  /**
   * Returns the names in the PHP storage without the '.php' extension.
   *
   * @param \Drupal\Component\PhpStorage\PhpStorageInterface $phpStorage
   *   The PHP storage.
   *
   * @return string[]
   *   The sorted names.
   */
  protected function listNames(PhpStorageInterface $phpStorage): array {
    $names = array_map(fn ($name) => str_ends_with($name, '.php') ? substr($name, 0, -4) : $name, $phpStorage->listAll());
    sort($names);
    return $names;
  }

  /**
   * Tests that load() returns NULL when no hash exists in the cache.
   */
  public function testLoadReturnsNullWhenNoCache(): void {
    $storage = new PhpDumperContainerStorage($this->cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_identifier'));
  }

  /**
   * Tests load() returns NULL when hash exists in cache but file is missing.
   */
  public function testLoadReturnsNullWhenFileMissing(): void {
    $this->cache->set('container_hash:test_identifier', 'abc123');
    $storage = new PhpDumperContainerStorage($this->cache, $this->createPhpStorage(), Settings::getInstance());
    $this->assertNull($storage->load('test_identifier'));
  }

  /**
   * Tests that a dumped container is stored, confirmed and can be loaded.
   *
   * @param string $storage_class
   *   The PhpStorageInterface implementation to use.
   */
  #[TestWith([MTimeProtectedFileStorage::class], 'MTimeProtectedFileStorage')]
  #[TestWith([FileStorage::class], 'FileStorage')]
  public function testDumpAndLoad(string $storage_class): void {
    $phpStorage = $this->createPhpStorage($storage_class);
    $storage = new PhpDumperContainerStorage($this->cache, $phpStorage, Settings::getInstance());
    $builder = $this->createContainerBuilder();
    $storage->dump($builder, 'test_identifier');

    $class = $this->getContainerClass('test_identifier');
    $this->assertSame([$class, $class . '.confirmed'], $this->listNames($phpStorage));

    $container = $storage->load('test_identifier');
    $this->assertInstanceOf($class, $container);
    $this->assertInstanceOf(PhpContainer::class, $container);
    $this->assertSame($builder->getParameter('test_param'), $container->getParameter('test_param'));
  }

  /**
   * Tests that dump() with the same hash rewrites only the container file.
   */
  public function testDumpSameHashRewritesContainerFile(): void {
    $phpStorage = $this->createPhpStorage(FileStorage::class);
    $storage = new PhpDumperContainerStorage($this->cache, $phpStorage, Settings::getInstance());
    $builder = $this->createContainerBuilder();
    $storage->dump($builder, 'test_identifier');
    $class = $this->getContainerClass('test_identifier');

    // Damage both files.
    file_put_contents($phpStorage->getFullPath($class . '.php'), '<?php class');
    file_put_contents($phpStorage->getFullPath($class . '.confirmed'), 'marker');

    // Dumping the same container again produces the same hash and therefore
    // the same filenames. The container file is rewritten, which repairs it,
    // but the existing confirmation marker is not.
    $storage->dump($builder, 'test_identifier');
    $this->assertStringContainsString('class ' . $class . ' ', file_get_contents($phpStorage->getFullPath($class . '.php')));
    $this->assertSame('marker', file_get_contents($phpStorage->getFullPath($class . '.confirmed')));
  }

  /**
   * Tests that dump() does not publish the hash when save() fails.
   */
  public function testDumpDoesNotPublishHashWhenSaveFails(): void {
    $phpStorage = $this->createStub(PhpStorageInterface::class);
    $phpStorage->method('save')->willReturn(FALSE);

    $storage = new PhpDumperContainerStorage($this->cache, $phpStorage, Settings::getInstance());
    $storage->dump($this->createContainerBuilder(), 'test_identifier');
    $this->assertFalse($this->cache->get('container_hash:test_identifier'));
  }

  /**
   * Tests that garbageCollection() removes all containers except the current.
   *
   * @param string $storage_class
   *   The PhpStorageInterface implementation to use.
   */
  #[TestWith([MTimeProtectedFileStorage::class], 'MTimeProtectedFileStorage')]
  #[TestWith([FileStorage::class], 'FileStorage')]
  public function testGarbageCollectionRemovesSupersededContainers(string $storage_class): void {
    $phpStorage = $this->createPhpStorage($storage_class);
    $storage = new PhpDumperContainerStorage($this->cache, $phpStorage, Settings::getInstance());

    // A container left behind by a previous identifier (e.g. an earlier
    // deployment).
    $storage->dump($this->createContainerBuilder(), 'old_identifier');

    // Two versions under the current identifier; the first is superseded by the
    // second. The storage is invalidated in between, as happens on a cache
    // rebuild, which must not stop the superseded container being removed.
    $storage->dump($this->createContainerBuilder(), 'current_identifier');
    $storage->invalidate();
    $storage->dump($this->createContainerBuilder(), 'current_identifier');
    $this->assertCount(6, $phpStorage->listAll());

    $storage->garbageCollection('current_identifier');

    $class = $this->getContainerClass('current_identifier');
    $this->assertSame([$class, $class . '.confirmed'], $this->listNames($phpStorage));
  }

  /**
   * Tests that garbageCollection() keeps an unconfirmed container.
   */
  public function testGarbageCollectionKeepsUnconfirmedContainer(): void {
    $phpStorage = $this->createPhpStorage();
    $storage = new PhpDumperContainerStorage($this->cache, $phpStorage, Settings::getInstance());
    $storage->dump($this->createContainerBuilder(), 'old_identifier');
    $storage->dump($this->createContainerBuilder(), 'current_identifier');

    // Simulate a concurrent dump() for the same identifier that has written its
    // file but has not yet published its hash to the cache or written its
    // confirmation marker.
    $phpStorage->save('DrupalContainer_unconfirmed.php', '<?php');

    $storage->garbageCollection('current_identifier');

    // The old, confirmed container and its marker are removed. The unconfirmed
    // container is kept.
    $class = $this->getContainerClass('current_identifier');
    $expected = [$class, $class . '.confirmed', 'DrupalContainer_unconfirmed'];
    sort($expected);
    $this->assertSame($expected, $this->listNames($phpStorage));
  }

}
