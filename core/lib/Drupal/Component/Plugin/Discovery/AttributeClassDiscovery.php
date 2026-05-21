<?php

namespace Drupal\Component\Plugin\Discovery;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\FileCache\FileCacheInterface;

/**
 * Defines a discovery mechanism to find plugins with attributes.
 */
class AttributeClassDiscovery implements DiscoveryInterface {

  use DiscoveryTrait;
  use AttributeClassDiscoveryTrait;

  /**
   * The file cache object.
   */
  protected FileCacheInterface $fileCache;

  /**
   * Constructs a new instance.
   *
   * @param array<string, list<string>> $pluginNamespaces
   *   (optional) An array of namespace that may contain plugin implementations.
   *   Defaults to an empty array.
   * @param string $pluginDefinitionAttributeName
   *   (optional) The name of the attribute that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Plugin\Attribute\Plugin'.
   */
  public function __construct(
    protected readonly array $pluginNamespaces = [],
    protected readonly string $pluginDefinitionAttributeName = Plugin::class,
  ) {
    $file_cache_suffix = str_replace('\\', '_', $this->pluginDefinitionAttributeName);
    $this->fileCache = FileCacheFactory::get('attribute_discovery:' . $this->getFileCacheSuffix($file_cache_suffix));
    $this->rootTwoLevelNamespaces = array_unique(array_map(fn($namespace) => $this->getTwoLevelNamespace($namespace), array_keys($this->getPluginNamespaces())));
  }

  /**
   * Gets the file cache suffix.
   *
   * This method allows classes that extend this class to add additional
   * information to the file cache collection name.
   *
   * @param string $default_suffix
   *   The default file cache suffix.
   *
   * @return string
   *   The file cache suffix.
   */
  protected function getFileCacheSuffix(string $default_suffix): string {
    return $default_suffix;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = [];

    // Search for classes within all PSR-4 namespace locations.
    foreach ($this->getPluginNamespaces() as $namespace => $dirs) {
      foreach ($dirs as $dir) {
        if (file_exists($dir)) {
          $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
          );
          foreach ($iterator as $fileinfo) {
            assert($fileinfo instanceof \SplFileInfo);
            if ($fileinfo->getExtension() === 'php') {
              if ($cached = $this->fileCache->get($fileinfo->getPathName())) {
                if (isset($cached['id'])) {
                  // Explicitly unserialize this to create a new object
                  // instance.
                  $dependencies = isset($cached['dependencies']) ? unserialize($cached['dependencies']) : [];
                  if (!$this->hasMissingDependencies($dependencies ?? [])) {
                    $definitions[$cached['id']] = unserialize($cached['content']);
                  }
                }
                continue;
              }

              $sub_path = $iterator->getSubIterator()->getSubPath();
              $sub_path = $sub_path ? str_replace(DIRECTORY_SEPARATOR, '\\', $sub_path) . '\\' : '';
              $class = $namespace . '\\' . $sub_path . $fileinfo->getBasename('.php');
              // Plugins may rely on classes, interfaces, or traits defined by
              // modules that are not installed. In such a case, an error may
              // be thrown from reflection. However, this is an unavoidable
              // situation with optional dependencies and plugins. Therefore,
              // silently skip over this class and avoid writing to the cache,
              // so that it is scanned each time. This ensures that the plugin
              // definition will be found if the module it requires is enabled.
              try {
                if (!class_exists($class)) {
                  continue;
                }
              }
              catch (\Error) {
                continue;
              }
              $result = $this->parseClass($class, $fileinfo);
              ['id' => $id, 'content' => $content] = $result;
              if ($id) {
                if (!$this->hasMissingDependencies($result['dependencies'] ?? [])) {
                  $definitions[$id] = $content;
                }
                // Explicitly serialize this to create a new object instance.
                $this->fileCache->set($fileinfo->getPathName(), [
                  'id' => $id,
                  'content' => serialize($content),
                  'dependencies' => serialize($result['dependencies'] ?? NULL),
                ]);
              }
              else {
                // Store a NULL object, so that the file is not parsed again.
                $this->fileCache->set($fileinfo->getPathName(), [NULL]);
              }
            }
          }
        }
      }
    }

    return $definitions;
  }

}
