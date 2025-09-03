<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin;

use Drupal\Core\Plugin\Discovery\LegacyDiscoveryDecorator;

/**
 * Helper trait for plugin managers that are deprecating a plugin subdir.
 *
 * Plugin managers can use this trait to deprecate a plugin subdir. A plugin
 * manager must do the following:
 * - Set the new plugin subdir as $this->subdir.
 * - Set the deprecated plugin subdir as $this->legacySubdir.
 * - Set the deprecation message as $this->deprecationMessage, with a '%s' for
 *   the plugin ID.
 */
trait LegacySubdirPluginManagerTrait {

  /**
   * The subdirectory which is being deprecated for the plugin type.
   */
  protected string $legacySubdir;

  /**
   * The message for a deprecation error, with '%s' for the plugin ID.
   */
  protected string $deprecationMessage;

  /**
   * Overrides \Drupal\Component\Plugin\PluginManagerBase::getDiscovery().
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $main_discovery = parent::getDiscovery();
      // Undo the setting of the discovery, so we can call the method again.
      $this->discovery = NULL;

      // Get legacy discovery.
      // Switcheroo the $subdir property so parent::getDiscovery() uses the
      // legacy subdir.
      $main_subdir = $this->subdir;
      $this->subdir = $this->legacySubdir;

      $legacy_discovery = parent::getDiscovery();
      $this->discovery = NULL;

      // Restore the subdir.
      $this->subdir = $main_subdir;

      $this->discovery = new LegacyDiscoveryDecorator(
        $main_discovery,
        $legacy_discovery,
        $this->deprecationMessage,
      );
    }

    return $this->discovery;
  }

}
