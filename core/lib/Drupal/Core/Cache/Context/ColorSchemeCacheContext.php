<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the ColorSchemeCacheContext service, for "per color scheme" caching.
 *
 * Cache context ID: 'user.color_scheme'.
 */
class ColorSchemeCacheContext extends UserCacheContextBase implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Color scheme');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->user->getColorScheme();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
