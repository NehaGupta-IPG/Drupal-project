<?php

namespace Drupal\Core\Cache\Context;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Ensures cache context tags have an 'id' attribute for the service locator.
 */
class CacheContextsPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $cache_contexts = [];
    foreach ($container->findTaggedServiceIds('cache.context') as $service_id => $tags) {
      $definition = $container->getDefinition($service_id);
      $new_tags = [];

      foreach ($tags as $attributes) {
        if (!isset($attributes['id'])) {
          if (!str_starts_with($service_id, 'cache_context.')) {
            throw new \InvalidArgumentException(sprintf('The service "%s" has an invalid service ID: cache context service IDs must use the "cache_context." prefix, or specify an "id" attribute on the tag.', $service_id));
          }
          $attributes['id'] = substr($service_id, 14);
        }
        $cache_contexts[] = $attributes['id'];
        $new_tags[] = $attributes;
      }

      $definition->clearTag('cache.context');
      foreach ($new_tags as $attributes) {
        $definition->addTag('cache.context', $attributes);
      }
    }

    // Validate.
    sort($cache_contexts);
    foreach ($cache_contexts as $id) {
      // Validate the hierarchy of non-root-level cache contexts.
      if (str_contains($id, '.')) {
        $parent = substr($id, 0, strrpos($id, '.'));
        if (!in_array($parent, $cache_contexts)) {
          throw new \InvalidArgumentException(sprintf('The service "%s" has an invalid service ID: the period indicates the hierarchy of cache contexts, therefore "%s" is considered the parent cache context, but no cache context service with that name was found.', $id, $parent));
        }
      }
    }
  }

}
