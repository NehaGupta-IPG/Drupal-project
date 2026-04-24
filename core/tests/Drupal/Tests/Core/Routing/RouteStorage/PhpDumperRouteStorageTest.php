<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Routing\RouteStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Routing\RouteStorage\CompiledRouterData;
use Drupal\Core\Routing\RouteStorage\PhpDumperRouteStorage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests \Drupal\Core\Routing\RouteStorage\PhpDumperRouteStorage.
 */
#[CoversClass(PhpDumperRouteStorage::class)]
#[Group('Routing')]
class PhpDumperRouteStorageTest extends UnitTestCase {

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
   * Builds a RouteCollection with a handful of routes and one alias.
   */
  protected function buildCollection(): RouteCollection {
    $collection = new RouteCollection();
    $collection->add('front', new Route('/'));
    $collection->add('node_canonical', new Route('/node/{node}', ['_controller' => 'node.view']));
    $collection->add('user_canonical', new Route('/user/{user}', ['_controller' => 'user.view']));
    $collection->addAlias('entity.node.canonical', 'node_canonical');
    return $collection;
  }

  /**
   * Creates an in-memory cache stub that round-trips set() and get().
   */
  protected function createInMemoryCache(?object &$cached = NULL): CacheBackendInterface {
    $cached = NULL;
    $cache = $this->createStub(CacheBackendInterface::class);
    $cache->method('set')
      ->willReturnCallback(function (string $cid, mixed $value) use (&$cached): void {
        $cached = (object) ['cid' => $cid, 'data' => $value];
      });
    $cache->method('get')
      ->willReturnCallback(function (string $cid) use (&$cached) {
        return ($cached && $cached->cid === $cid) ? $cached : FALSE;
      });
    return $cache;
  }

  /**
   * Creates a PhpStorage stub that backs saves with a temp file on disk.
   */
  protected function createPhpStorage(array &$savedFiles = [], array &$deletedFiles = []): PhpStorageInterface {
    $paths = [];
    $phpStorage = $this->createStub(PhpStorageInterface::class);

    $phpStorage->method('save')
      ->willReturnCallback(function (string $filename, string $code) use (&$paths, &$savedFiles): bool {
        if (isset($paths[$filename])) {
          file_put_contents($paths[$filename], $code);
        }
        else {
          $temp = tempnam(sys_get_temp_dir(), 'drupal_route_test_');
          file_put_contents($temp, $code);
          $paths[$filename] = $temp;
          $this->tempFiles[] = $temp;
        }
        $savedFiles[] = $filename;
        return TRUE;
      });

    $phpStorage->method('getFullPath')
      ->willReturnCallback(function (string $filename) use (&$paths): string|false {
        return $paths[$filename] ?? FALSE;
      });

    $phpStorage->method('exists')
      ->willReturnCallback(function (string $filename) use (&$paths): bool {
        return isset($paths[$filename]);
      });

    $phpStorage->method('delete')
      ->willReturnCallback(function (string $filename) use (&$paths, &$deletedFiles): bool {
        if (isset($paths[$filename])) {
          @unlink($paths[$filename]);
          unset($paths[$filename]);
        }
        $deletedFiles[] = $filename;
        return TRUE;
      });

    return $phpStorage;
  }

  /**
   * Builds a storage using the standard stubs.
   */
  protected function buildStorage(CacheBackendInterface $cache, PhpStorageInterface $phpStorage): PhpDumperRouteStorage {
    return new PhpDumperRouteStorage($cache, $phpStorage);
  }

  /**
   * Tests that load() returns NULL when nothing is cached.
   */
  public function testLoadReturnsNullOnMiss(): void {
    $cache = $this->createStub(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $storage = $this->buildStorage($cache, $this->createPhpStorage());
    $this->assertNull($storage->load());
  }

  /**
   * Tests a dump/load round trip through a stubbed PhpStorage.
   */
  public function testDumpThenLoadRoundTrip(): void {
    $cache = $this->createInMemoryCache();
    $saved = [];
    $phpStorage = $this->createPhpStorage($saved);
    $storage = $this->buildStorage($cache, $phpStorage);

    $data = $storage->dump($this->buildCollection());
    $this->assertInstanceOf(CompiledRouterData::class, $data);
    $this->assertCount(1, $saved);

    // Re-instantiate to force a file-backed load path.
    $loaded = $this->buildStorage($cache, $phpStorage)->load();
    $this->assertInstanceOf(CompiledRouterData::class, $loaded);
    $this->assertSame('/node/{node}', $loaded->getRoute('node_canonical')?->getPath());
    $this->assertSame('node_canonical', $loaded->getAliases()['entity.node.canonical']);
  }

  /**
   * Tests that a re-dump of unchanged routes does not write a new file.
   */
  public function testDumpSkipsWriteWhenHashMatches(): void {
    $cache = $this->createInMemoryCache();
    $saved = [];
    $storage = $this->buildStorage($cache, $this->createPhpStorage($saved));
    $storage->dump($this->buildCollection());
    $storage->dump($this->buildCollection());
    $this->assertCount(1, $saved, 'Second dump should not write a new file for identical routes.');
  }

  /**
   * Tests that destruct() deletes stale files tracked from previous dumps.
   */
  public function testDestructDeletesStaleFiles(): void {
    $cache = $this->createInMemoryCache();
    $saved = [];
    $deleted = [];
    $storage = $this->buildStorage($cache, $this->createPhpStorage($saved, $deleted));

    $storage->dump($this->buildCollection());

    $altered = $this->buildCollection();
    $altered->add('extra', new Route('/extra'));
    $storage->dump($altered);

    $this->assertCount(2, $saved, 'A different route set should be written as a new file.');
    $this->assertEmpty($deleted);

    $storage->destruct();
    $this->assertCount(1, $deleted, 'destruct() should delete the superseded file.');
    $this->assertSame($saved[0], $deleted[0]);
  }

  /**
   * Tests that invalidate() deletes the cache entry for this site.
   */
  public function testInvalidate(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())->method('delete');
    $storage = $this->buildStorage($cache, $this->createPhpStorage());
    $storage->invalidate();
  }

}
