<?php

namespace Drupal\Core\Extension;

/**
 * Provides a list of installation profiles.
 *
 * @internal
 *
 * @ingroup extension_api
 */
class ProfileExtensionList extends ExtensionList {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'dependencies' => [],
    'install' => [],
    'description' => '',
    'package' => 'Other',
    'version' => NULL,
    'php' => \Drupal::MINIMUM_PHP,
  ];

  /**
   * {@inheritdoc}
   */
  protected function getInstalledExtensionNames() {
    return [$this->installProfile];
  }

}
