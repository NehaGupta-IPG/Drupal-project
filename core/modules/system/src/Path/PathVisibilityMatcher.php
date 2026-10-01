<?php

declare(strict_types=1);

namespace Drupal\system\Path;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Matches the current path against the page patterns of a request_path.
 *
 * Both the request_path condition and the url.path.matches cache context use
 * it, so the decision and the cache variation cannot disagree.
 *
 * @see \Drupal\system\Plugin\Condition\RequestPath
 * @see \Drupal\system\Cache\Context\PathMatchesCacheContext
 */
class PathVisibilityMatcher {

  /**
   * Constructs a PathVisibilityMatcher.
   *
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface|null $aliasManager
   *   The alias manager, or NULL while the path_alias module is not installed:
   *   the container must also compile then, as it does in the installer.
   */
  public function __construct(
    protected PathMatcherInterface $pathMatcher,
    protected RequestStack $requestStack,
    protected CurrentPathStack $currentPath,
    protected ?AliasManagerInterface $aliasManager = NULL,
  ) {}

  /**
   * Checks whether the current path matches the given page patterns.
   *
   * @param string $pages
   *   The lowercased page patterns.
   *
   * @return bool
   *   TRUE if the current path or its alias matches.
   */
  public function matches(string $pages): bool {
    $request = $this->requestStack->getCurrentRequest();
    $path = $this->currentPath->getPath($request);
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');
    // The internal path is known, while its alias may cost a lookup: only
    // compare the lowercase alias, if any, when the internal path fails.
    if ($this->pathMatcher->matchPath($path, $pages)) {
      return TRUE;
    }
    if ($this->aliasManager === NULL) {
      return FALSE;
    }
    $path_alias = mb_strtolower($this->aliasManager->getAliasByPath($path));
    return $path !== $path_alias && $this->pathMatcher->matchPath($path_alias, $pages);
  }

}
