<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Defines the SessionCacheContext service, for "per session" caching.
 *
 * Cache context ID: 'session'.
 */
class SessionCacheContext extends RequestStackCacheContextBase implements CacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Session');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return Crypt::hashBase64($this->requestStack->getSession()->getId());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $metadata = new CacheableMetadata();
    if (!$this->requestStack->getSession()->getId()) {
      $metadata->setCacheMaxAge(0);
    }
    return $metadata;
  }

}
