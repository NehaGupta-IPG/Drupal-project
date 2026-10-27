<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the PseudoCacheContext service.
 *
 * This cache context will always return the value of '1', but is not intended
 * to be added manually. Instead, users are expected to use the associated
 * helper class \Drupal\Core\Cache\DependencyVariation instead.
 *
 * Cache context ID: 'pseudo'.
 *
 * @see \Drupal\Core\Cache\DependencyVariation
 */
class PseudoCacheContext implements CalculatedCacheContextInterface {

  const ID = 'pseudo';

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Pseudo');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    return 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    return new CacheableMetadata();
  }

}
