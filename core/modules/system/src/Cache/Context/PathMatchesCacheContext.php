<?php

declare(strict_types=1);

namespace Drupal\system\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Path\PathPatternsMap;
use Drupal\system\Path\PathVisibilityMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the PathMatchesCacheContext service, for "per match result" caching.
 *
 * Cache context ID: 'url.path.matches:%hash', where %hash identifies the page
 * patterns of a request_path condition. It varies by whether the current path
 * matches them: two variations instead of one per path, 404 pages included.
 *
 * The parameter is a hash rather than the patterns themselves, because the
 * token is written into every cache ID and cacheability metadata carrying it.
 *
 * @see \Drupal\system\Path\PathPatternsMap
 * @see \Drupal\system\Path\PathVisibilityMatcher
 * @see \Drupal\system\Plugin\Condition\RequestPath
 */
class PathMatchesCacheContext implements CalculatedCacheContextInterface {

  public function __construct(
    protected PathPatternsMap $map,
    protected PathVisibilityMatcher $matcher,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return new TranslatableMarkup('Path matches page patterns');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    $pages = $parameter === NULL ? NULL : $this->map->get($parameter);
    if ($pages === NULL) {
      // No provider knows these patterns: vary like url.path, which is never
      // wrong. A path starts with "/", so it cannot collide with "0" or "1".
      $request = $this->requestStack->getCurrentRequest();
      return $request->getBasePath() . $request->getPathInfo();
    }
    return $this->matcher->matches($pages) ? '1' : '0';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    $metadata = new CacheableMetadata();
    $pages = $parameter === NULL ? NULL : $this->map->get($parameter);
    // The <front> keyword resolves through the front page setting.
    if ($pages !== NULL && str_contains($pages, '<front>')) {
      $metadata->addCacheTags(['config:system.site']);
    }
    return $metadata;
  }

}
