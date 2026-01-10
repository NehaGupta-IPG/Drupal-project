<?php

namespace Drupal\Core\Plugin\Discovery;

use Drupal\Core\File\FileSystemInterface;

/**
 * Discover directories that contain a specific metadata file.
 */
class DirectoryWithMetadataPluginDiscovery extends YamlDiscovery {

  public function __construct(array $directories, $file_cache_key_suffix, ?FileSystemInterface $file_system = NULL) {
    // Intentionally does not call parent constructor as this class uses a
    // different YAML discovery.
    if ($file_system) {
      @trigger_error(sprintf('Passing the $fileSystem parameter to %s() is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. The class no longer uses the file system service. See https://www.drupal.org/node/3530869', __METHOD__), E_USER_DEPRECATED);
    }
    $this->discovery = new DirectoryWithMetadataDiscovery($directories, $file_cache_key_suffix);
  }

}
