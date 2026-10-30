<?php

namespace Drupal\Core\Config;

/**
 * Provides an interface for the configuration comparator.
 */
interface ConfigComparatorInterface {

  /**
   * Checks if a config item has been modified since its installation.
   *
   * @param string $config_name
   *   The name of the configuration object.
   *
   * @return bool
   *   Returns TRUE is modified, FALSE if original configuration.
   *
   * @throws ConfigNameException
   *   Thrown when the configuration is not found.
   */
  public function isModified(string $config_name);

}
