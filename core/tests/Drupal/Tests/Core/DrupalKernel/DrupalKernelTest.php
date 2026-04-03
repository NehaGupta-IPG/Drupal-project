<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\DrupalKernel;

use Composer\Autoload\ClassLoader;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests Drupal\Core\DrupalKernel.
 */
#[CoversClass(DrupalKernel::class)]
#[Group('DrupalKernel')]
class DrupalKernelTest extends UnitTestCase {

  /**
   * Tests hostname validation with settings.
   *
   * @legacy-covers ::setupTrustedHosts
   */
  #[DataProvider('providerTestTrustedHosts')]
  public function testTrustedHosts($host, $server_name, string $message, $expected = FALSE): void {
    $request = new Request();

    $trusted_host_patterns = [
      '^example\.com$',
      '^.+\.example\.com$',
      '^example\.org',
      '^.+\.example\.org',
    ];

    if (!empty($host)) {
      $request->headers->set('HOST', $host);
    }

    $request->server->set('SERVER_NAME', $server_name);

    $method = new \ReflectionMethod('Drupal\Core\DrupalKernel', 'setupTrustedHosts');
    $valid_host = $method->invoke(NULL, $request, $trusted_host_patterns);

    $this->assertSame($expected, $valid_host, $message);

    // Reset the trusted hosts because it is statically stored on the request.
    $method->invoke(NULL, $request, []);
    // Reset the request factory because it is statically stored on the request.
    Request::setFactory(NULL);
  }

  /**
   * Provides test data for testTrustedHosts().
   */
  public static function providerTestTrustedHosts(): array {
    $data = [];

    // Tests canonical URL.
    $data[] = [
      'www.example.com',
      'www.example.com',
      'canonical URL is trusted',
      TRUE,
    ];

    // Tests missing hostname for HTTP/1.0 compatibility where the Host
    // header is optional.
    $data[] = [NULL, 'www.example.com', 'empty Host is valid', TRUE];

    // Tests the additional patterns from the settings.
    $data[] = [
      'example.com',
      'www.example.com',
      'host from settings is trusted',
      TRUE,
    ];
    $data[] = [
      'subdomain.example.com',
      'www.example.com',
      'host from settings is trusted',
      TRUE,
    ];
    $data[] = [
      'www.example.org',
      'www.example.com',
      'host from settings is trusted',
      TRUE,
    ];
    $data[] = [
      'example.org',
      'www.example.com',
      'host from settings is trusted',
      TRUE,
    ];

    // Tests mismatch.
    $data[] = [
      'www.black_hat.com',
      'www.example.com',
      'unspecified host is untrusted',
      FALSE,
    ];

    return $data;
  }

  /**
   * Tests site path finding.
   *
   * This test is run in a separate process since it defines DRUPAL_ROOT. This
   * stops any possible pollution of other tests.
   */
  #[RunInSeparateProcess]
  public function testFindSitePath(): void {
    $vfs_root = vfsStream::setup('drupal_root');
    $sites_php = <<<'EOD'
<?php
$sites['8888.www.example.org'] = 'example';
EOD;

    // Create the expected directory structure.
    vfsStream::create([
      'sites' => [
        'sites.php' => $sites_php,
        'example' => [
          'settings.php' => 'test',
        ],
      ],
    ]);

    $request = new Request();
    $request->server->set('SERVER_NAME', 'www.example.org');
    $request->server->set('SERVER_PORT', '8888');
    $request->server->set('SCRIPT_NAME', '/index.php');
    $this->assertEquals('sites/example', DrupalKernel::findSitePath($request, TRUE, $vfs_root->url()));
    $this->assertEquals('sites/example', DrupalKernel::findSitePath($request, FALSE, $vfs_root->url()));
  }

  /**
   * Tests un booted terminate.
   *
   * @legacy-covers ::terminate
   */
  #[RunInSeparateProcess]
  public function testUnBootedTerminate(): void {
    $kernel = new DrupalKernel('test', new ClassLoader());
    $kernel->terminate(new Request(), new Response());
    $this->assertTrue(TRUE, "\Drupal\Core\DrupalKernel::terminate() called without error on kernel which has not booted");
  }

  /**
   * Ensures APCu hit with valid timestamp skips the container DB query.
   */
  public function testApcuHitWithValidTimestamp(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $expected = ['foo' => 'bar'];
    $now = round(microtime(TRUE), 3);
    $timestamp_cid = 'last_write_timestamp_' . $kernel->getTestContainerCacheKey();

    // APCu has the definition; DB has an older timestamp.
    $kernel->primeApcuStore(['data' => $expected, 'created' => $now]);
    $cache->prime($timestamp_cid, $now - 1);

    $this->assertSame($expected, $kernel->getCachedContainerDefinition());
    // Only the timestamp was fetched from DB, not the container definition.
    $this->assertSame([$timestamp_cid], $cache->getCids);
  }

  /**
   * Ensures APCu hit works when created equals last_write (common case).
   */
  public function testApcuHitWithEqualTimestamp(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $expected = ['service' => 'definition'];
    $now = round(microtime(TRUE), 3);
    $timestamp_cid = 'last_write_timestamp_' . $kernel->getTestContainerCacheKey();

    // Both timestamps identical — the typical case after cacheDrupalContainer().
    $kernel->primeApcuStore(['data' => $expected, 'created' => $now]);
    $cache->prime($timestamp_cid, $now);

    $this->assertSame($expected, $kernel->getCachedContainerDefinition());
    $this->assertSame([$timestamp_cid], $cache->getCids);
  }

  /**
   * Ensures stale APCu entries are skipped when the DB timestamp is newer.
   */
  public function testApcuHitWithStaleTimestamp(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $new_data = ['fresh' => TRUE];
    $container_cid = $kernel->getTestContainerCacheKey();
    $timestamp_cid = 'last_write_timestamp_' . $container_cid;

    // APCu has old data (created=1000), DB has newer timestamp (2000).
    $kernel->primeApcuStore(['data' => ['stale' => TRUE], 'created' => 1000.0]);
    $cache->prime($timestamp_cid, 2000.0);
    $cache->prime($container_cid, $new_data);

    $this->assertSame($new_data, $kernel->getCachedContainerDefinition());
    // First get: timestamp. Second get: container definition from DB.
    $this->assertSame([$timestamp_cid, $container_cid], $cache->getCids);
  }

  /**
   * Ensures APCu is skipped when the timestamp is missing (after deleteAll).
   */
  public function testApcuSkippedWhenTimestampMissing(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $container_cid = $kernel->getTestContainerCacheKey();
    $timestamp_cid = 'last_write_timestamp_' . $container_cid;

    // APCu has data but no timestamp in DB (simulates post-deleteAll).
    $kernel->primeApcuStore(['data' => ['old' => TRUE], 'created' => 1000.0]);
    $cache->prime($container_cid, ['rebuilt' => TRUE]);

    $this->assertSame(['rebuilt' => TRUE], $kernel->getCachedContainerDefinition());
    // Timestamp miss then container fetch from DB.
    $this->assertSame([$timestamp_cid, $container_cid], $cache->getCids);
  }

  /**
   * Ensures fallback to DB when APCu is empty, with APCu backfill.
   */
  public function testApcuMissBackfillsFromDb(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $cache->prime($kernel->getTestContainerCacheKey(), ['from_db' => TRUE]);

    $this->assertSame(['from_db' => TRUE], $kernel->getCachedContainerDefinition());

    // APCu was backfilled with data and a created timestamp.
    $entry = $kernel->getApcuEntry();
    $this->assertSame(['from_db' => TRUE], $entry['value']['data']);
    $this->assertArrayHasKey('created', $entry['value']);
  }

  /**
   * Ensures NULL is returned when both APCu and DB are empty.
   */
  public function testReturnsNullWhenBothEmpty(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);

    $this->assertNull($kernel->getCachedContainerDefinition());
  }

  /**
   * Ensures cacheDrupalContainer() writes definition + timestamp to DB and APCu.
   */
  public function testCacheDrupalContainerWritesBothStores(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $container_cid = $kernel->getTestContainerCacheKey();
    $timestamp_cid = 'last_write_timestamp_' . $container_cid;
    $definition = ['service' => 'value'];

    $this->assertTrue($kernel->cacheDefinition($definition));

    // DB received both the definition and the timestamp.
    $this->assertSame([$container_cid, $timestamp_cid], $cache->setCids);

    // APCu received the definition with a created timestamp.
    $entry = $kernel->getApcuEntry();
    $this->assertSame($definition, $entry['value']['data']);
    $this->assertArrayHasKey('created', $entry['value']);
  }

  /**
   * Ensures a round-trip: write via cacheDrupalContainer, read from APCu.
   */
  public function testWriteThenReadRoundTrip(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $definition = ['services' => ['foo' => 'bar']];

    // Write the container (populates both DB and APCu).
    $kernel->cacheDefinition($definition);

    // Reset tracking so the read assertions are clean.
    $cache->getCids = [];

    // Read should hit APCu, validated by the timestamp in DB.
    $this->assertSame($definition, $kernel->getCachedContainerDefinition());
    // Only the timestamp was fetched, not the full container definition.
    $timestamp_cid = 'last_write_timestamp_' . $kernel->getTestContainerCacheKey();
    $this->assertSame([$timestamp_cid], $cache->getCids);
  }

  /**
   * Ensures corrupted APCu entries are ignored and DB is used instead.
   */
  public function testCorruptedApcuEntryFallsToDb(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $container_cid = $kernel->getTestContainerCacheKey();
    $cache->prime($container_cid, ['from_db' => TRUE]);

    // APCu has data but it's not in the expected format (missing 'data' key).
    $kernel->primeApcuStore('not_an_array');

    $this->assertSame(['from_db' => TRUE], $kernel->getCachedContainerDefinition());
    // Falls through to DB because APCu entry is malformed.
    $this->assertSame([$container_cid], $cache->getCids);
  }

  /**
   * Ensures everything works when APCu is unavailable (key returns NULL).
   */
  public function testFallbackWhenApcuUnavailable(): void {
    $cache = new StubCacheBackend();
    $kernel = new DrupalKernelNoApcuTestKernel('test', new ClassLoader());
    $kernel->setSitePath('sites/default');
    $container = new SymfonyContainer();
    $container->set('cache.container', $cache);
    $kernel->setTestBootstrapContainer($container);

    $container_cid = $kernel->getTestContainerCacheKey();
    $cache->prime($container_cid, ['db_only' => TRUE]);

    $this->assertSame(['db_only' => TRUE], $kernel->getCachedContainerDefinition());
    // Went straight to DB, no APCu interaction.
    $this->assertSame([$container_cid], $cache->getCids);
  }

  /**
   * Ensures invalidateContainer() clears both APCu and DB.
   */
  public function testInvalidateContainerClearsBothStores(): void {
    $cache = new StubCacheBackend();
    $kernel = $this->createApcuKernel($cache);
    $kernel->primeApcuStore(['data' => ['value' => TRUE], 'created' => 1.0]);
    $kernel->setBootedState(TRUE);

    $kernel->invalidateContainer();

    $this->assertSame(1, $cache->deleteAllCount);
    $this->assertNull($kernel->getApcuEntry());
  }

  /**
   * Helper to create a kernel with an injectable cache backend.
   */
  private function createApcuKernel(StubCacheBackend $cache): DrupalKernelApcuTestKernel {
    $kernel = new DrupalKernelApcuTestKernel('test', new ClassLoader());
    $kernel->setSitePath('sites/default');
    $container = new SymfonyContainer();
    $container->set('cache.container', $cache);
    $kernel->setTestBootstrapContainer($container);
    return $kernel;
  }

}

/**
 * A fake autoloader for testing.
 */
class FakeAutoloader {

  /**
   * Registers this instance as an autoloader.
   *
   * @param bool $prepend
   *   Whether to prepend the autoloader or not.
   */
  public function register($prepend = FALSE): void {
    spl_autoload_register([$this, 'loadClass'], TRUE, $prepend);
  }

  /**
   * Deregisters this instance as an autoloader.
   */
  public function unregister(): void {
    spl_autoload_unregister([$this, 'loadClass']);
  }

  /**
   * Loads the given class or interface.
   *
   * This class never loads.
   */
  public function loadClass(): NULL {
    return NULL;
  }

  /**
   * Finds a file by class name while caching lookups to APC.
   *
   * This class never finds.
   */
  public function findFile(): NULL {
    return NULL;
  }

}

/**
 * Simple cache backend used for APCu-related tests.
 */
class StubCacheBackend implements CacheBackendInterface {

  /**
   * The stored cache items.
   *
   * @var array
   */
  protected $items = [];

  /**
   * CIDs passed to get(), in order.
   *
   * @var string[]
   */
  public array $getCids = [];

  /**
   * CIDs passed to set(), in order.
   *
   * @var string[]
   */
  public array $setCids = [];

  /**
   * Number of times deleteAll() has been called.
   */
  public int $deleteAllCount = 0;

  /**
   * Primes a cache entry.
   *
   * @param string $cid
   *   The cache ID.
   * @param mixed $value
   *   The cached value.
   */
  public function prime(string $cid, $value): void {
    $this->items[$cid] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $this->getCids[] = $cid;
    if (!array_key_exists($cid, $this->items)) {
      return FALSE;
    }
    return (object) [
      'cid' => $cid,
      'data' => $this->items[$cid],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $items = [];
    foreach ($cids as $cid) {
      if ($item = $this->get($cid, $allow_invalid)) {
        $items[$cid] = $item;
      }
    }
    $cids = array_diff($cids, array_keys($items));
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []): void {
    $this->setCids[] = $cid;
    $this->items[$cid] = $data;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items): void {
    foreach ($items as $cid => $item) {
      $this->set($cid, $item['data'], $item['expire'] ?? CacheBackendInterface::CACHE_PERMANENT, $item['tags'] ?? []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid): void {
    unset($this->items[$cid]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids): void {
    foreach ($cids as $cid) {
      $this->delete($cid);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): void {
    $this->deleteAllCount++;
    $this->items = [];
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid): void {
    // No-op for the stub.
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids): void {
    // No-op for the stub.
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags): void {
    // No-op for the stub.
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection(): void {
    // No-op for the stub.
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin(): void {
    $this->items = [];
  }

}

/**
 * Test kernel that injects an in-memory APCu implementation.
 */
class DrupalKernelApcuTestKernel extends DrupalKernel {

  /**
   * In-memory store that mimics APCu.
   *
   * @var array
   */
  protected $apcuStorage = [];

  /**
   * Overrides the APCu key used during the test.
   *
   * @var string
   */
  protected $apcuTestKey = 'drupal_kernel_test_apcu';

  /**
   * Sets the bootstrap container for testing.
   */
  public function setTestBootstrapContainer(ContainerInterface $container): void {
    $this->bootstrapContainer = $container;
  }

  /**
   * Exposes the container cache key for priming test data.
   */
  public function getTestContainerCacheKey(): string {
    return $this->getContainerCacheKey();
  }

  /**
   * Marks the kernel as booted or not.
   */
  public function setBootedState(bool $booted): void {
    $this->booted = $booted;
  }

  /**
   * Seeds the in-memory APCu store.
   *
   * @param mixed $value
   *   The cached value.
   */
  public function primeApcuStore($value): void {
    $this->apcuStorage[$this->apcuTestKey] = [
      'value' => $value,
    ];
  }

  /**
   * Returns the current APCu entry, if any.
   */
  public function getApcuEntry(): ?array {
    return $this->apcuStorage[$this->apcuTestKey] ?? NULL;
  }

  /**
   * Public wrapper for cacheDrupalContainer().
   */
  public function cacheDefinition(array $definition): bool {
    return $this->cacheDrupalContainer($definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function getContainerApcuKey(): ?string {
    return $this->apcuTestKey;
  }

  /**
   * {@inheritdoc}
   */
  protected function containerApcuFetch(string $key, ?bool &$success = NULL): mixed {
    if (isset($this->apcuStorage[$key])) {
      $success = TRUE;
      return $this->apcuStorage[$key]['value'];
    }
    $success = FALSE;
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function containerApcuStore(string $key, mixed $value): bool {
    $this->apcuStorage[$key] = [
      'value' => $value,
    ];
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function containerApcuDelete(string $key): bool {
    unset($this->apcuStorage[$key]);
    return TRUE;
  }

}

/**
 * Test kernel that simulates APCu being unavailable.
 */
class DrupalKernelNoApcuTestKernel extends DrupalKernelApcuTestKernel {

  /**
   * {@inheritdoc}
   */
  protected function getContainerApcuKey(): ?string {
    return NULL;
  }

}
