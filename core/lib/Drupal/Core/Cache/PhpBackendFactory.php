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
   * @var CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * Constructs a PhpBackendFactory object.
   *
   * @param CacheTagsChecksumInterface $checksum_provider
   *   The cache tags checksum provider.
   * @param TimeInterface $time
   *   The time service.
   */
  public function __construct(CacheTagsChecksumInterface $checksum_provider, protected TimeInterface $time) {
    $this->checksumProvider = $checksum_provider;
  }

  /**
   * Gets PhpBackend for the specified cache bin.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   *
   * @return PhpBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new PhpBackend($bin, $this->checksumProvider, $this->time);
  }

}
