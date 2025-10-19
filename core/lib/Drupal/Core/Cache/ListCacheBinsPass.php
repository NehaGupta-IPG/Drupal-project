<?php

namespace Drupal\Core\Cache;

use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds cache_bins parameter to the container.
 */
class ListCacheBinsPass implements CompilerPassInterface {

  /**
   * Implements CompilerPassInterface::process().
   *
   * Collects the cache bins into the cache_bins parameter.
   */
  public function process(ContainerBuilder $container): void {
    $cache_info['cache']['bins'] = [];
    $cache_info['cache']['default_bin_backends'] = [];
    $cache_info['memory_cache']['bins'] = [];
    $cache_info['memory_cache']['default_bin_backends'] = [];

    $cacheFactory = $container->getDefinition(DelegatedCacheFactory::class);

    $tag_info = [
      'cache.bin' => 'cache',
      'cache.bin.memory' => 'memory_cache',
    ];
    $all_bins = [];
    foreach ($tag_info as $service_tag => $section) {
      foreach ($container->findTaggedServiceIds($service_tag) as $id => $attributes) {
        if (!isset($attributes[0]['bin'])) {
          // Detect bin name from legacy X.bin_name service naming convention.
          $attributes[0]['bin'] = substr($id, strpos($id, '.') + 1);
          @trigger_error('Service "' . $id . '" omits the bin tag from the service definition which is deprecated in drupal:11.0.0 and support will be removed in drupal:12.0.0. See https://www.drupal.org/project/drupal/issues/3272093', E_USER_DEPRECATED);
        }
        $bin = $attributes[0]['bin'];
        if (in_array($bin, $all_bins, TRUE)) {
          throw new \InvalidArgumentException(sprintf('A cache bin named "%s" is already declared. Cache bin names must be unique across all cache types.', $bin));
        }
        $all_bins[] = $bin;
        $cache_info[$section]['bins'][$id] = $bin;
        // Inject cache bin into delegated factory service.
        $cacheFactory->addMethodCall('offsetSet', [$bin, new ServiceClosureArgument(new Reference($id))]);
        if (isset($attributes[0]['default_backend'])) {
          $cache_info[$section]['default_bin_backends'][$bin] = $attributes[0]['default_backend'];
        }
      }
    }

    $container->setParameter('cache_bins', $cache_info['cache']['bins']);
    $container->setParameter('cache_default_bin_backends', $cache_info['cache']['default_bin_backends']);
    $container->setParameter('memory_cache_bins', $cache_info['memory_cache']['bins']);
    $container->setParameter('memory_cache_default_bin_backends', $cache_info['memory_cache']['default_bin_backends']);
  }

}
