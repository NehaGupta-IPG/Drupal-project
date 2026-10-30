<?php

namespace Drupal\Core\Config;

use Drupal\Component\Utility\Crypt;

/**
 * Provides methods to compare configuration.
 */
class ConfigComparator implements ConfigComparatorInterface {

  /**
   * The active configuration storage.
   */
  protected StorageInterface $activeStorage;

  /**
   * Creates a ConfigComparator object.
   *
   * @param \Drupal\Core\Config\StorageInterface $active_storage
   *   The active configuration storage.
   */
  public function __construct(StorageInterface $active_storage) {
    $this->activeStorage = $active_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function isModified($config_name) {
    $active = $this->activeStorage->read($config_name);

    if (!$active) {
      throw new ConfigNameException(
        sprintf('Configuration "%s" does not exist.', $config_name)
      );
    }

    // Get the hash created when the config was installed.
    $original_hash = $active['_core']['default_config_hash'];

    // Remove export keys not used to generate default config hash.
    unset($active['uuid']);
    unset($active['_core']);
    $active_hash = Crypt::hashBase64(serialize($active));

    return $original_hash !== $active_hash;
  }

}
