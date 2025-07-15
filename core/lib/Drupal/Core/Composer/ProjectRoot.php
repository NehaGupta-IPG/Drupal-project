<?php

namespace Drupal\Core\Composer;

use Drupal\Core\Composer\ProjectRootInterface;

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
      if ($autoload instanceof \Composer\Autoload\ClassLoader) {
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
  public function getInstalledPackages() {
    $installed_json_path = $this->getProjectVendorDirectory() . '/composer/installed.json';

    // If installed.json does not exist, no packages are installed.
    if (!file_exists($installed_json_path)) {
      return [];
    }

    $installed_packages = [];
    $packages = [];

    $json = file_get_contents($installed_json_path, FALSE);
    if ($json !== FALSE) {
      $packages = json_decode($json);
    }
    if (!empty($packages)) {
      foreach ($packages as $package) {
        if (isset($package->name) && isset($package->version_normalized)) {
          $installed_packages[$package->name] = $package->version_normalized;
        }
      }
    }
    return $installed_packages;
  }

  /**
   * {@inheritdoc}
   */
  public function getDevRequirements() {
    // Since the default setup for Drupal is to have both a root package and a
    // pre-installed package for drupal/core, we have to merge requirements to
    // emulate the composer-merge plugin.
    // @todo: Possibly change this when the drupal/core subtree split happens in
    // https://www.drupal.org/node/2352091
    return array_merge(
      $this->getDevRequires($this->appRoot . '/composer.json'),
      $this->getDevRequires($this->appRoot . '/core/composer.json')
    );
  }

  /**
   * Gathers the dev dependencies from a composer.json file.
   *
   * This method should never throw exceptions, since there are many different
   * ways to structure Drupal as a Composer-based project.
   *
   * @param string $composer_file
   *   Path to the composer.json file to read in.
   *
   * @return string[]
   *   Associative array where the keys are the package names and the values are
   *   version constraints. This is similar to the composer.json file's
   *   require-dev array.
   *
   * @see https://getcomposer.org/doc/04-schema.md#require-dev
   */
  protected function getDevRequires($composer_file) {
    $contents = file_get_contents($composer_file, FALSE);
    if ($contents === FALSE) {
      return [];
    }
    $json = json_decode($contents, TRUE);
    if (isset($json['require-dev'])) {
      return $json['require-dev'];
    }
    return [];
  }

}
