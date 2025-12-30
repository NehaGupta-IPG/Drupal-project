<?php

namespace Drupal\Core\Cache;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Defines a PHP cache backend factory.
 */
class PhpBackendFactory implements CacheFactoryInterface {

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  public function __construct(CacheTagsChecksumInterface $checksum_provider, protected TimeInterface $time) {
    $this->checksumProvider = $checksum_provider;
  }

  /**
   * Gets PhpBackend for the specified cache bin.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\Core\Cache\PhpBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new PhpBackend($bin, $this->checksumProvider, $this->time);
  }

}
