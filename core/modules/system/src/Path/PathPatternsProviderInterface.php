<?php

declare(strict_types=1);

namespace Drupal\system\Path;

/**
 * Provides the page patterns of the request_path conditions a module stores.
 *
 * The url.path.matches cache context only receives a hash of the patterns, so
 * it needs providers to find the patterns back. Services implementing this
 * interface must be tagged with 'path_patterns_provider'.
 *
 * @see \Drupal\system\Path\PathPatternsMap
 * @see \Drupal\system\Cache\Context\PathMatchesCacheContext
 */
interface PathPatternsProviderInterface {

  /**
   * Returns the page patterns of the request_path conditions in use.
   *
   * @return string[]
   *   The 'pages' setting of each condition, as stored.
   */
  public function getPatterns(): array;

  /**
   * Returns the cache tags invalidated when these patterns change.
   *
   * @return string[]
   *   A list of cache tags.
   */
  public function getCacheTags(): array;

}
