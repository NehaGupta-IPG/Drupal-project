<?php

namespace Drupal\media;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\media\Attribute\MediaSource;

/**
 * Manages media source plugins.
 */
class MediaSourceManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/media/Source', $namespaces, $module_handler, MediaSourceInterface::class, MediaSource::class, '\Drupal\media\Annotation\MediaSource');

    $this->alterInfo('media_source_info');
    $this->setCacheBackend($cache_backend, 'media_source_plugins');
  }

}
