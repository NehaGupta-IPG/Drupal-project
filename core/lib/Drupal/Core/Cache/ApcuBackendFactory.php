<?php

namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;

/**
 * Defines the APCU backend factory.
 */
class ApcuBackendFactory implements CacheFactoryInterface {

  /**
   * The site prefix string.
   *
   * @var string
   */
  protected $sitePrefix;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The APCU backend class to use.
   *
   * @var string
   */
  protected $backendClass;

  public function __construct($root, $site_path, CacheTagsChecksumInterface $checksum_provider, protected TimeInterface $time) {
    $this->sitePrefix = Settings::getApcuPrefix('apcu_backend', $root, $site_path);
    $this->checksumProvider = $checksum_provider;
    $this->backendClass = 'Drupal\Core\Cache\ApcuBackend';
  }

  /**
   * Gets ApcuBackend for the specified cache bin.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\Core\Cache\ApcuBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new $this->backendClass($bin, $this->sitePrefix, $this->checksumProvider, $this->time);
  }

}
