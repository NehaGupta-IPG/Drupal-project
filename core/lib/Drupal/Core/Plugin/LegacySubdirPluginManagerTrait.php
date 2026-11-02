<?php

declare(strict_types=1);

namespace Drupal\Core\Plugin;

use Drupal\Core\Plugin\Discovery\LegacyDiscoveryDecorator;

/**
 * Helper trait for plugin managers that are deprecating a plugin subdir.
 *
 * Plugin managers can use this trait to deprecate a plugin subdir. A plugin
 * manager must do the following:
 * - Set the new plugin subdir as $this->subdir as normal.
 * - Set the deprecated plugin subdir as $this->legacySubdir.
 * - Set the deprecation version as $this->deprecationVersion.
 * - Set the removal version as $this->removedVersion.
 * - Set the change record URL as $this->changeRecordUrl.
 */
trait LegacySubdirPluginManagerTrait {

  /**
   * The plugin subdirectory which is being deprecated for the plugin type.
   */
  protected string $legacySubdir;

  /**
   * The version in which the deprecation was introduced.
   */
  protected string $deprecationVersion;

  /**
   * The version in which the backwards-compatibility handling will be removed.
   */
  protected string $removedVersion;

  /**
   * The URL for the change record.
   */
  protected string $changeRecordUrl;

  /**
   * Overrides \Drupal\Component\Plugin\PluginManagerBase::getDiscovery().
   */
  protected function getDiscovery() {
    if (!$this->discovery) {
      $main_discovery = parent::getDiscovery();
      // Undo the setting of the discovery, so calling getDiscovery() again
      // creates another discover object.
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
        "in subdirectory {$this->legacySubdir}",
        "The plugin class should be moved to {$this->subdir} and its namespace adjusted accordingly.",
        $this->deprecationVersion,
        $this->removedVersion,
        $this->changeRecordUrl,
      );
    }

    return $this->discovery;
  }

}
