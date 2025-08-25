<?php

namespace Drupal\Core\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Defines the TimeZoneCacheContext service, for "per time zone" caching.
 *
 * Cache context ID: 'timezone'.
 *
 * @see \Drupal\Core\Session\AccountProxy::setAccount()
 */
class TimeZoneCacheContext implements CacheContextInterface, CacheContextOptimizableInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor for the TimeZoneCacheContext object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t("Time zone");
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // date_default_timezone_set() is called in AccountProxy::setAccount(), so
    // we can safely retrieve the timezone.
    return date_default_timezone_get();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    $cacheability_metadata = new CacheableMetadata();
    $cacheability_metadata->addCacheableDependency($this->configFactory->get('system.date'));
    return $cacheability_metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function hasVariations(): bool {
    // The timezone context can not have different values if the site does not
    // use configurable timezones.
    return (bool) $this->configFactory->get('system.date')->get('timezone.user.configurable');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentContexts(): array {
    return [];
  }

}
