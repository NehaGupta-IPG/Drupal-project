<?php

declare(strict_types=1);

namespace Drupal\system\Path;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Maps page pattern hashes back to the patterns of request_path conditions.
 *
 * The map is derived data: the patterns stay in the configuration of whatever
 * uses the condition, and the collected providers expose them.
 *
 * The cached map carries no cache tags, because validating them would cost a
 * lookup on every request reading it. It is deleted instead when one of the
 * providers' cache tags is invalidated. A map missing a hash is never wrong:
 * the cache context then varies by path.
 *
 * @see \Drupal\system\Path\PathPatternsProviderInterface
 * @see \Drupal\system\Cache\Context\PathMatchesCacheContext
 */
class PathPatternsMap implements CacheTagsInvalidatorInterface {

  /**
   * The cache ID prefix of the map.
   */
  public const string CID = 'system.path_patterns_map';

  /**
   * The collected providers.
   *
   * @var \Drupal\system\Path\PathPatternsProviderInterface[]
   */
  protected array $providers = [];

  /**
   * The map for this request, keyed by hash.
   *
   * @var array<string, string>|null
   */
  protected ?array $map = NULL;

  public function __construct(
    #[Autowire(service: 'cache.bootstrap')]
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Adds a provider of page patterns.
   *
   * @param \Drupal\system\Path\PathPatternsProviderInterface $provider
   *   The provider.
   */
  public function addProvider(PathPatternsProviderInterface $provider): void {
    $this->providers[] = $provider;
    $this->map = NULL;
  }

  /**
   * Hashes page patterns into a cache context parameter.
   *
   * Patterns are lowercased first, as the request_path condition does before
   * matching them.
   *
   * @param string $pages
   *   The page patterns.
   *
   * @return string
   *   A hash of fixed length, which contains no colon.
   */
  public static function hash(string $pages): string {
    return Crypt::hashBase64(mb_strtolower($pages));
  }

  /**
   * Returns the page patterns for a hash.
   *
   * @param string $hash
   *   A hash from ::hash().
   *
   * @return string|null
   *   The lowercased page patterns, or NULL if no provider has them.
   */
  public function get(string $hash): ?string {
    $this->map ??= $this->load();
    return $this->map[$hash] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags): void {
    if ($this->providers && array_intersect($tags, $this->getCacheTags())) {
      $this->cache->delete($this->getCid());
      $this->map = NULL;
    }
  }

  /**
   * Returns the cache tags of all providers.
   *
   * @return string[]
   *   A sorted list of cache tags.
   */
  protected function getCacheTags(): array {
    $tags = [];
    foreach ($this->providers as $provider) {
      $tags = Cache::mergeTags($tags, $provider->getCacheTags());
    }
    sort($tags);
    return $tags;
  }

  /**
   * Returns the cache ID of the map.
   *
   * The providers' tags are part of it, so that a map built for one set of
   * providers is not served to another.
   */
  protected function getCid(): string {
    return static::CID . ':' . implode(',', $this->getCacheTags());
  }

  /**
   * Loads the map from cache, building it on a miss.
   *
   * @return array<string, string>
   *   The lowercased page patterns, keyed by hash.
   */
  protected function load(): array {
    if (!$this->providers) {
      return [];
    }
    $cid = $this->getCid();
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }
    $map = [];
    foreach ($this->providers as $provider) {
      foreach ($provider->getPatterns() as $pages) {
        $pages = mb_strtolower($pages);
        // The condition adds no cache context for empty patterns.
        if ($pages) {
          $map[static::hash($pages)] = $pages;
        }
      }
    }
    $this->cache->set($cid, $map);
    return $map;
  }

}
