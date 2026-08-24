<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the default implementation of the active menu trail service.
 *
 * It uses the current route name and route parameters to compare with the ones
 * of the menu links.
 */
class MenuActiveTrail extends CacheCollector implements MenuActiveTrailInterface {

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The route match object for the current page.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a \Drupal\Core\Menu\MenuActiveTrail object.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link plugin manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A route match object for finding the active link.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path.matcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request_stack service.
   */
  public function __construct(
    MenuLinkManagerInterface $menu_link_manager,
    RouteMatchInterface $route_match,
    CacheBackendInterface $cache,
    LockBackendInterface $lock,
    protected PathMatcherInterface $pathMatcher,
    protected RequestStack $requestStack,
  ) {
    parent::__construct(NULL, $cache, $lock);
    $this->menuLinkManager = $menu_link_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   *
   * @see ::getActiveTrailIds()
   */
  protected function getCid() {
    if (!isset($this->cid)) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      ksort($route_parameters);
      $this->cid = 'active-trail:route:' . $this->routeMatch->getRouteName() . ':route_parameters:' . serialize($route_parameters);
      // The active trail can differ per query string.
      if ($query = $this->requestStack->getCurrentRequest()?->query->all()) {
        ksort($query);
        $this->cid .= ':query:' . serialize($query);
      }
    }

    return $this->cid;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    // Because this cache is not additive, it stores exactly the same thing
    // every time, there is no need to acquire a lock before writing.
    $this->updateCache(FALSE);
  }

  /**
   * {@inheritdoc}
   *
   * @see ::getActiveTrailIds()
   */
  protected function resolveCacheMiss($menu_name) {
    $this->storage[$menu_name] = $this->doGetActiveTrailIds($menu_name);
    if (empty($menu_name)) {
      // We look in every menu so invalidate when any menu or menu item changes.
      $this->tags[] = 'config:menu_list';
      $this->tags[] = 'menu_link_content_list';
    }
    else {
      $this->tags[] = 'config:system.menu.' . $menu_name;
    }
    $this->persist($menu_name);

    return $this->storage[$menu_name];
  }

  /**
   * {@inheritdoc}
   *
   * This implementation caches all active trail IDs per route match for *all*
   * menus whose active trails are calculated on that page. This ensures 1 cache
   * get for all active trails per page load, rather than N.
   *
   * It uses the cache collector pattern to do this.
   *
   * @see ::get()
   * @see \Drupal\Core\Cache\CacheCollectorInterface
   * @see \Drupal\Core\Cache\CacheCollector
   */
  public function getActiveTrailIds($menu_name) {
    // A NULL menu name correlates to cache entry keyed by an empty string. See
    // ::doGetActiveTrailIds().
    $menu_name = $menu_name ?? '';
    return $this->get($menu_name);
  }

  /**
   * Helper method for ::getActiveTrailIds().
   */
  protected function doGetActiveTrailIds($menu_name) {
    // Parent ids; used both as key and value to ensure uniqueness.
    // We always want all the top-level links with parent == ''.
    $active_trail = ['' => ''];

    // If a link in the given menu indeed matches the route, then use it to
    // complete the active trail.
    if ($active_link = $this->getActiveLink($menu_name)) {
      if ($parents = $this->menuLinkManager->getParentIds($active_link->getPluginId())) {
        $active_trail = $parents + $active_trail;
      }
    }

    return $active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveLink($menu_name = NULL) {
    // Note: this is a very simple implementation. If you need more control
    // over the return value, such as matching a prioritized list of menu names,
    // you should substitute your own implementation for the 'menu.active_trail'
    // service in the container.
    // The menu links coming from the storage are already sorted by depth,
    // weight and ID.
    $links = [];

    $route_name = $this->routeMatch->getRouteName();
    // On a default (not custom) 403 page the route name is NULL. On a custom
    // 403 page we will get the route name for that page, so we can consider
    // it a feature that a relevant menu tree may be displayed.
    if ($route_name) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();

      // Load links matching this route.
      $links = $this->menuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name);
    }

    // If the request is for the site's front page, then menu links containing
    // <front> must also be loaded since it's a special route that's an alias
    // for the page.
    // @todo Combine the two calls to loadLinksByRoute() in
    // https://www.drupal.org/project/drupal/issues/3523497
    if ($this->pathMatcher->isFrontPage()) {
      $links = array_merge($links, $this->menuLinkManager->loadLinksByRoute('<front>', [], $menu_name));
    }

    // Several menu links can share a route and differ only in their query
    // options. Prefer an enabled link whose query options are all present in
    // the current request, so that unrelated parameters such as a pager do not
    // break the match.
    $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    foreach ($links as $link) {
      $link_query = $link->getOptions()['query'] ?? [];
      if ($link_query && $link->isEnabled() && array_intersect_key($query, $link_query) == $link_query) {
        return $link;
      }
    }

    // Select the first enabled matching link.
    foreach ($links as $link) {
      if ($link->isEnabled()) {
        return $link;
      }
    }

    return NULL;
  }

}
