<?php

namespace Drupal\layout_builder\Cache;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides a cache tag invalidator that clears the block cache.
 *
 * @internal
 *   Tagged services are internal.
 */
class ExtraFieldBlockCacheTagInvalidator implements CacheTagsInvalidatorInterface {

  /**
   * Constructs a new ExtraFieldBlockCacheTagInvalidator.
   *
   * @param \Closure $blockManager
   *   A closure returning the block manager. This service is created together
   *   with cache_tags.invalidator, and the block manager depends on the logger,
   *   so loading it eagerly can cause a circular reference.
   */
  public function __construct(
    #[AutowireServiceClosure('plugin.manager.block')]
    protected \Closure $blockManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if (in_array('entity_field_info', $tags, TRUE)) {
      $block_manager = ($this->blockManager)();
      if ($block_manager instanceof CachedDiscoveryInterface) {
        $block_manager->clearCachedDefinitions();
      }
    }
  }

}
