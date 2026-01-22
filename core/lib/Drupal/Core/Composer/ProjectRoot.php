<?php

namespace Drupal\Core\Composer;

use Composer\Autoload\ClassLoader;
use Drupal\Component\Serialization\Json;

/**
 * Provides information about the Composer project root.
 *
 * @internal
 */
class ProjectRoot implements ProjectRootInterface {

  /**
   * The full path to the root directory of the Drupal installation.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The full path to the vendor directory for this Drupal installation.
   *
   * @var string
   */
  protected $vendorDirectory;

  /**
   * Construct a ProjectRoot object.
   *
   * @param string $app_root
   *   Full path to the Drupal root directory.
   */
  public function __construct($app_root) {
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectVendorDirectory() {
    if (empty($this->vendorDirectory)) {
      // In order to discover the vendor directory, we require the Drupal class
      // loader facade which is always in appRoot. appRoot/autoload.php
      // is supposed to pass through the actual class loader, wherever it might
      // be in the file system. Once we have this class loader object, we can
      // use reflection to deduce the location of vendor/.
      $vendor_dir = $this->appRoot . '/vendor';
      $autoload = require $this->appRoot . '/autoload.php';
      if ($autoload instanceof ClassLoader) {
        $ref_loader = new \ReflectionClass($autoload);
        $vendor_dir = dirname(dirname($ref_loader->getFileName()));
      }
      $this->vendorDirectory = $vendor_dir;
    }
    return $this->vendorDirectory;
  }

  /**
   * {@inheritdoc}
   */
  public function isDevInstall(): bool {
    $installed_json_path = $this->getProjectVendorDirectory() . '/composer/installed.json';

    // If installed.json does not exist, no packages are installed.
    if (!file_exists($installed_json_path)) {
      return FALSE;
    }

    $json = file_get_contents($installed_json_path);
    if ($json !== FALSE) {
      $packages = Json::decode($json);

      if (isset($packages['dev'])) {
        return $packages['dev'];
      }
    }

    return FALSE;
  }

}
