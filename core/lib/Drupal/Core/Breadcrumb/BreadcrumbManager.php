<?php

namespace Drupal\Core\Breadcrumb;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a breadcrumb manager.
 *
 * Can be assigned any number of BreadcrumbBuilderInterface objects by calling
 * the addBuilder() method. When build() is called it iterates over the objects
 * in priority order and uses the first one that returns TRUE from
 * BreadcrumbBuilderInterface::applies() to build the breadcrumbs.
 *
 * @see \Drupal\Core\DependencyInjection\Compiler\RegisterBreadcrumbBuilderPass
 */
class BreadcrumbManager implements ChainBreadcrumbBuilderInterface {

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Holds arrays of breadcrumb builders, keyed by priority.
   *
   * @var array
   */
  protected $builders = [];

  /**
   * Holds the array of breadcrumb builders sorted by priority.
   *
   * Set to NULL if the array needs to be re-calculated.
   *
   * @var \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface[]|null
   */
  protected $sortedBuilders;

  /**
   * The memory cache used to statically cache built breadcrumbs.
   */
  protected MemoryCacheInterface $memoryCache;

  /**
   * Constructs a \Drupal\Core\Breadcrumb\BreadcrumbManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface|null $memory_cache
   *   The memory cache.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ?MemoryCacheInterface $memory_cache = NULL) {
    $this->moduleHandler = $module_handler;
    if (!$memory_cache) {
      @trigger_error('Calling ' . __METHOD__ . '() without the $memory_cache argument is deprecated in drupal:11.4.0 and will be required in drupal:13.0.0. See https://www.drupal.org/node/3621448', E_USER_DEPRECATED);
      $memory_cache = \Drupal::service('cache.memory');
    }
    $this->memoryCache = $memory_cache;
  }

  /**
   * {@inheritdoc}
   */
  public function addBuilder(BreadcrumbBuilderInterface $builder, $priority) {
    $this->builders[$priority][] = $builder;
    // Force the builders to be re-sorted.
    $this->sortedBuilders = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, CacheableMetadata $cacheable_metadata) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $cid = $this->getCacheId($route_match);
    if ($cid && ($cache = $this->memoryCache->get($cid))) {
      // Return a copy so that callers cannot alter the cached breadcrumb.
      return clone $cache->data;
    }

    $cacheable_metadata = new CacheableMetadata();
    $breadcrumb = new Breadcrumb();
    $context = ['builder' => NULL];
    // Call the build method of registered breadcrumb builders,
    // until one of them returns an array.
    foreach ($this->getSortedBuilders() as $builder) {
      if (!$builder->applies($route_match, $cacheable_metadata)) {
        // The builder does not apply, so we continue with the other builders.
        continue;
      }

      $breadcrumb = $builder->build($route_match);
      if ($breadcrumb instanceof Breadcrumb) {
        $context['builder'] = $builder;
        break;
      }
      else {
        throw new \UnexpectedValueException('Invalid breadcrumb returned by ' . get_class($builder) . '::build().');
      }
    }

    // Ensure all collected cacheability is applied.
    $breadcrumb->addCacheableDependency($cacheable_metadata);

    // Allow modules to alter the breadcrumb.
    $this->moduleHandler->alter('system_breadcrumb', $breadcrumb, $route_match, $context);

    if ($cid) {
      $this->memoryCache->set($cid, clone $breadcrumb, MemoryCacheInterface::CACHE_PERMANENT, $breadcrumb->getCacheTags());
    }

    return $breadcrumb;
  }

  /**
   * Returns the static cache ID for the breadcrumb of a route match.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string|null
   *   The cache ID, or NULL if the breadcrumb should not be cached.
   */
  protected function getCacheId(RouteMatchInterface $route_match): ?string {
    $route_name = $route_match->getRouteName();
    if ($route_name === NULL) {
      return NULL;
    }
    $raw_parameters = $route_match->getRawParameters()?->all() ?? [];
    return 'breadcrumb:' . $route_name . ':' . hash('xxh64', serialize($raw_parameters));
  }

  /**
   * Returns the sorted array of breadcrumb builders.
   *
   * @return \Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface[]
   *   An array of breadcrumb builder objects.
   */
  protected function getSortedBuilders() {
    if (!isset($this->sortedBuilders)) {
      // Sort the builders according to priority.
      krsort($this->builders);
      // Merge nested builders from $this->builders into $this->sortedBuilders.
      $this->sortedBuilders = array_merge(...$this->builders);
    }
    return $this->sortedBuilders;
  }

}
