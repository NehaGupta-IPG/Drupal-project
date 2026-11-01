<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Utility;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Discovery\YamlCacheCollectorDiscovery;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Lock\NullLockBackend;
use Drupal\Core\Utility\YamlCacheCollector;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Cache\CacheCollector.
 */
#[CoversClass(CacheCollector::class)]
#[Group('Cache')]
class YamlCacheCollectorTest extends UnitTestCase {

  /**
   * The cache backend that should be used.
   */
  protected CacheBackendInterface $cacheBackend;

  /**
   * The lock backend that should be used.
   */
  protected LockBackendInterface $lock;

  /**
   * The time instance that should be used.
   */
  protected TimeInterface $time;

  /**
   * The cache id used for the test.
   *
   * @var string
   */
  protected $cid;

  /**
   * Cache collector implementation to test.
   *
   * @var \Drupal\Tests\Core\Cache\CacheCollectorHelper
   */
  protected $collector;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->time = new Time();
    $this->cacheBackend = new MemoryBackend($this->time);
    $this->lock = new NullLockBackend();
    $this->cid = 'test';
    $this->collector = new YamlCacheCollector($this->cid, $this->cacheBackend, $this->lock, $this->time);
  }

  /**
   * Tests the ::updateCache() function.
   */
  public function testUpdateCache(): void {
    $this->cacheBackend->set('key', [
      '/foo/bar/foo_bar.txt' => [
        'data' => [TRUE],
        'mtime' => 12345,
      ],
    ]);

    $this->collector->set('/foo/bar/foo_bar.txt', [
      'data' => ['hello'],
      'mtime' => 12345,
    ]);
    $this->collector->updateCache();
    $this->assertSame($this->collector->get('/foo/bar/foo_bar.txt'), []);

    $cached = $this->cacheBackend->get('key');
    $this->assertArrayNotHasKey('foo_bar.txt', $cached->data);
  }

  /**
   * Tests that discovery destruction does not read time after cache reset.
   */
  public function testDiscoveryDestructionAfterCacheReset(): void {
    $directory = vfsStream::setup('modules', NULL, [
      'example.links.menu.yml' => "example.link:\n  title: Example\n",
    ]);
    $time = $this->createMock(TimeInterface::class);
    // Only the initial cache write needs the current time. A decorated time
    // service may query the database after its connected has closed
    // during shutdown.
    $time->expects($this->once())
      ->method('getCurrentTime')
      ->willReturn($this->time->getCurrentTime());
    $collector = new YamlCacheCollector($this->cid, $this->cacheBackend, $this->lock, $time);
    $discovery = new YamlCacheCollectorDiscovery('links.menu', ['example' => $directory->url()], $collector);

    $this->assertSame([
      'example' => ['example.link' => ['title' => 'Example']],
    ], $discovery->findAll());

    // findAll() has already flushed and reset the collector. Destruction must
    // not access the time service again if there is no remaining cache work.
    unset($discovery);
  }

}
