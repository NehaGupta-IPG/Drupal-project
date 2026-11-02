<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Defines a cache context for varying by a path-matching result.
 *
 * Unlike the 'url.path' cache context, which varies by the exact request
 * path (producing an unbounded number of cache entries, one per unique URL
 * ever visited -- most notably for 404 pages), this context only varies by
 * whether the current path matches a given set of path patterns.
 *
 * Calculated cache context ID: 'path_condition:%patterns', e.g.
 * 'path_condition:/node/*' (to vary by whether the current path matches
 * the given path patterns).
 */
class PathConditionCacheContext extends RequestStackCacheContextBase implements CalculatedCacheContextInterface {

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Constructs a PathConditionCacheContext object.
   *
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(PathMatcherInterface $path_matcher, $request_stack) {
    parent::__construct($request_stack);
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Path condition match');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($patterns = NULL) {
    if ($patterns === NULL || $patterns === '') {
      return 'no-patterns';
    }
    $path = $this->requestStack->getCurrentRequest()->getPathInfo();
    return $this->pathMatcher->matchPath($path, $patterns) ? 'match' : 'no-match';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($patterns = NULL) {
    return new CacheableMetadata();
  }

}
