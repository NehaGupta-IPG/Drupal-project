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
 * The compiled file returns an array with four keys:
 * - 'routes'  : name => serialize()'d Route
 * - 'aliases' : alias name => target name
 * - 'outlines': pattern outline => [['name' => string, 'fit' => int,
 *               'numParts' => int]]
 * - 'masks'   : sorted list of fit bitmasks that actually correspond to
 *               routes
 *
 * Mirroring the container-storage pattern, the file's hash is stored in a
 * cache backend so the next request can locate it, and stale files are
 * cleaned up on destruct().
 */
class PhpDumperRouteStorage implements RouteStorageInterface, DestructableInterface {

  /**
   * The cache prefix used to store the compiled-routes hash.
   */
  protected const string CACHE_PREFIX = 'routes_hash:';

  /**
   * CompiledRouterData instances currently loaded in this request.
   *
   * Keyed by hash; lets a rebuilt-in-the-same-request container reuse the
   * last dump without re-reading from disk.
   *
   * @var array<string, \Drupal\Core\Routing\RouteStorage\CompiledRouterData>
   */
  protected array $dataByHash = [];

  /**
   * The hash of the currently loaded router data set.
   */
  protected ?string $loadedHash = NULL;

  /**
   * Hashes of stale files to delete on destruct().
   *
   * Files become stale when a subsequent dump produces a different hash in
   * the same request.
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
  public function load(string $suffix): ?CompiledRouterData {
    $cache = $this->cache->get(static::CACHE_PREFIX . $suffix);
    if (!$cache || !$cache->data) {
      return NULL;
    }
    $hash = $cache->data;

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

    $data = new CompiledRouterData(
      $payload['routes'] ?? [],
      $payload['aliases'] ?? [],
      $payload['outlines'] ?? [],
      $payload['masks'] ?? [],
    );
    $this->dataByHash[$hash] = $data;
    $this->loadedHash = $hash;
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function dump(RouteCollection $routes, string $suffix): CompiledRouterData {
    $data = CompiledRouterData::fromCollection($routes);
    $code = $this->generateCode($data);
    $hash = hash('xxh128', $code);
    $filename = $this->fileName($hash);

    if ($hash === $this->loadedHash && isset($this->dataByHash[$hash])) {
      return $this->dataByHash[$hash];
    }

    if (isset($this->oldHashes[$hash]) && $this->phpStorage->exists($filename)) {
      unset($this->oldHashes[$hash]);
    }
    elseif (!$this->phpStorage->exists($filename)) {
      if ($this->phpStorage->save($filename, $code) === FALSE) {
        // Another worker may have written the file; fall through to the
        // in-memory representation for this request.
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
      $this->cache->set(static::CACHE_PREFIX . $suffix, $hash);
    }
    catch (\Exception) {
      // The cache bin may not exist yet (e.g. during install).
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(): void {
    $this->cache->deleteAll();
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
    $payload = [
      'routes' => $data->getSerializedRoutes(),
      'aliases' => $data->getAliases(),
      'outlines' => $data->getOutlineIndex(),
      'masks' => $data->getNumberPartsMasks(),
    ];
    return "<?php\n\n// This file is auto-generated.\n\nreturn " . var_export($payload, TRUE) . ";\n";
  }

  /**
   * Includes the compiled-routes file and returns its array payload.
   *
   * PhpStorageInterface::load() boolifies the file's return value, so we
   * include the path directly. This is safe because
   * MTimeProtectedFileStorage::exists() has already validated the mtime-based
   * integrity of the file.
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
   * Returns the filename used for a given hash.
   */
  protected function fileName(string $hash): string {
    return 'DrupalRoutes_' . $hash . '.php';
  }

}
