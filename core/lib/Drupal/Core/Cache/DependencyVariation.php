<?php

namespace Drupal\Core\Cache;

use Drupal\Core\Cache\Context\PseudoCacheContext;

/**
 * Helper class to add a pseudo cache context as a dependency.
 *
 * This is intended to be used when you have a code flow that varies based on a
 * (property of a) dependency, but you have no way to represent said variation
 * with a regular cache context. An example would be the passed in entity to an
 * access control handler: You cannot know where this entity came from, so how
 * would you know what cache context to use?
 *
 * You are supposed to use this helper class to wrap the original dependency and
 * then pass in this class as a cacheable dependency itself. It will then
 * compile the cache context identifier for you and add it to the cacheable
 * metadata this helper class was added to.
 *
 * @ingroup cache
 */
class DependencyVariation implements CacheableDependencyInterface {

  use CacheableDependencyTrait;

  public function __construct(CacheableDependencyInterface $dependency) {
    $this->cacheTags = $dependency->getCacheTags();
    $this->cacheMaxAge = $dependency->getCacheMaxAge();
    assert(empty($dependency->getCacheContexts()), 'A dependency passed into DependencyVariation should not contain any cache contexts.');
    sort($this->cacheTags);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $identifier = implode(',', $this->cacheTags) . '|' . $this->cacheMaxAge;
    return PseudoCacheContext::ID . ':' . $identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * Checks if a cache context identifier represents a pseudo cache context.
   *
   * @param string $cache_context
   *   The cache context identifier.
   *
   * @return bool
   *   Whether the cache context identifier is for a pseudo cache context.
   */
  public static function isPseudoCacheContext(string $cache_context): bool {
    return str_starts_with($cache_context, PseudoCacheContext::ID . ':');
  }

  /**
   * Parses a pseudo cache context into a cacheable dependency.
   *
   * @param string $cache_context
   *   The cache context identifier.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheable metadata that was embedded in the identifier. Note that
   *   this will only ever contain cache tags and max-age.
   */
  public static function parsePseudoCacheContext(string $cache_context): CacheableMetadata {
    [, $parameter] = explode(':', $cache_context, 2);
    [$cache_tag_string, $max_age] = explode('|', $parameter);
    return (new CacheableMetadata())
      ->addCacheTags(explode(',', $cache_tag_string))
      ->setCacheMaxAge($max_age);
  }

}
