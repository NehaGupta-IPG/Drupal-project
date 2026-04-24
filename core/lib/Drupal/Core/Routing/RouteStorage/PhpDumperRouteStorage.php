<?php

declare(strict_types=1);

namespace Drupal\Core\Routing\RouteStorage;

use Drupal\Component\PhpStorage\PhpStorageInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DestructableInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route storage that writes a compiled PHP file via PhpStorage.
 *
 * Two-level design so multi-web-head deployments stay consistent across
 * route rebuilds:
 * - PhpStorage holds each head's local copy of the compiled file, named
 *   by a content hash so independent heads with identical route data
 *   converge on the same filename.
 * - A shared cache backend (typically `cache.bootstrap`, which is
 *   chainedfast-backed so APCu reads are invalidated automatically across
 *   heads via the bin's last-write timestamp) records the current content
 *   hash under a fixed cache key. After head A rebuilds, head B's next
 *   request reads the new hash, finds its local file missing, and
 *   rebuilds locally — producing the same file and hash.
 *
 * A fixed cache key is sufficient because per-site isolation is already
 * provided at the database level: each Drupal site has its own DB (or DB
 * prefix), and therefore its own `cache_bootstrap` table. A cache flush
 * (`drush cr`) wipes the bin; the rebuild-on-miss path in
 * \Drupal\Core\Routing\CompiledRouteProvider repopulates it on the next
 * request.
 *
 * The compiled file returns an array with four keys:
 * - 'routes'  : name => serialize()'d Route or Alias
 * - 'aliases' : alias name => target name
 * - 'outlines': pattern outline => [['name' => string, 'fit' => int,
 *               'numParts' => int]]
 * - 'masks'   : sorted list of fit bit masks that actually correspond to
 *               routes
 */
class PhpDumperRouteStorage implements RouteStorageInterface, DestructableInterface {

  /**
   * The cache key used to record the compiled-routes hash.
   */
  protected const string CACHE_KEY = 'routes_hash';

  /**
   * CompiledRouterData instances materialized this request, keyed by hash.
   *
   * @var array<string, \Drupal\Core\Routing\RouteStorage\CompiledRouterData>
   */
  protected array $dataByHash = [];

  /**
   * Hash of the data most recently loaded or dumped.
   */
  protected ?string $loadedHash = NULL;

  /**
   * Hashes of stale files to delete on destruct().
   *
   * @var array<string, true>
   */
  protected array $oldHashes = [];

  public function __construct(
    protected readonly CacheBackendInterface $cache,
    protected readonly PhpStorageInterface $phpStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function load(): ?CompiledRouterData {
    $cached = $this->cache->get(static::CACHE_KEY);
    if (!$cached || !$cached->data) {
      return NULL;
    }
    $hash = $cached->data;

    if (isset($this->dataByHash[$hash])) {
      $this->loadedHash = $hash;
      return $this->dataByHash[$hash];
    }

    $filename = $this->fileName($hash);
    if (!$this->phpStorage->exists($filename)) {
      return NULL;
    }
    $payload = $this->includeFile($filename);
    if (!is_array($payload)) {
      return NULL;
    }

    $data = CompiledRouterData::fromPayload($payload);
    $this->dataByHash[$hash] = $data;
    $this->loadedHash = $hash;
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(RouteCollection $routes): CompiledRouterData {
    $data = CompiledRouterData::fromCollection($routes);
    $code = $this->generateCode($data);
    $hash = hash('xxh128', $code);
    $filename = $this->fileName($hash);

    if ($hash === $this->loadedHash && isset($this->dataByHash[$hash])) {
      return $this->dataByHash[$hash];
    }

    // If this hash was pending deletion from a prior dump in the same
    // request, reclaim it — the file is still on disk.
    if (isset($this->oldHashes[$hash]) && $this->phpStorage->exists($filename)) {
      unset($this->oldHashes[$hash]);
    }
    elseif (!$this->phpStorage->exists($filename)) {
      if ($this->phpStorage->save($filename, $code) === FALSE) {
        // Another worker may have written the file concurrently; continue
        // with the in-memory representation for this request.
        $this->dataByHash[$hash] = $data;
        return $data;
      }
    }

    if ($this->loadedHash !== NULL && $this->loadedHash !== $hash) {
      $this->oldHashes[$this->loadedHash] = TRUE;
    }
    $this->loadedHash = $hash;
    $this->dataByHash[$hash] = $data;

    try {
      $this->cache->set(static::CACHE_KEY, $hash);
    }
    catch (\Exception) {
      // The cache backend may not be available yet (e.g. during install).
      // Subsequent requests will rebuild locally on cache miss.
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->delete(static::CACHE_KEY);
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    foreach (array_keys($this->oldHashes) as $hash) {
      $this->phpStorage->delete($this->fileName($hash));
    }
    $this->oldHashes = [];
  }

  /**
   * Renders the compiled data to PHP source.
   */
  protected function generateCode(CompiledRouterData $data): string {
    return "<?php\n\n// This file is auto-generated.\n\nreturn " . var_export($data->toPayload(), TRUE) . ";\n";
  }

  /**
   * Includes the compiled-routes file and returns its array payload.
   *
   * PhpStorageInterface::load() casts the file's return value to a bool, so
   * we include the path directly to keep the returned array. This is safe
   * because MTimeProtectedFileStorage::exists() has already validated the
   * mtime-based integrity of the file.
   */
  protected function includeFile(string $filename): ?array {
    $path = $this->phpStorage->getFullPath($filename);
    if (!$path || !file_exists($path)) {
      return NULL;
    }
    $result = @include $path;
    return is_array($result) ? $result : NULL;
  }

  /**
   * Returns the filename used for a given content hash.
   */
  protected function fileName(string $hash): string {
    return 'DrupalRoutes_' . $hash . '.php';
  }

}
