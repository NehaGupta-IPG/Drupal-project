<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\IO\IOInterface;
use Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldResult;

/**
 * Generates the DrupalLocation file which defines Drupal location constants.
 *
 * Drupal and the application root may be installed in various locations, and at
 * runtime it is not easy to determine these. Part of the difficulty is that
 * Composer can symlink packages in, and PHP's file location constants such as
 * __DIR__ resolve symlinks.
 *
 * The authority on the location of packages is Composer, since it puts them in
 * their locations, and the authority on the location of the application root is
 * this scaffolding plugin, since it reads it from the composer.json file.
 *
 * Reading the locations from composer.json is possible at runtime, but is
 * undesirable for performance. Therefore, during the Composer install process
 * when we have access this data, this plugin writes a PHP class file which
 * defines the locations as class constants.
 *
 * The class file is written into the Composer project root. To be loadable, it
 * must be defined to the autoloader in the project's composer.json:
 * @code
 * "autoload": {
 *     "psr-4": {
 *         "Drupal\\Locations\\": ""
 *     }
 * },
 * @endcode
 *
 * To ensure the project's codebase is portable, the generated class must not
 * use absolute paths. It should instead use the __DIR__ constant, which in the
 * generated class will give the project root.
 *
 * @internal
 */
class ManageDrupalLocations {

  /**
   * ManageDrupalLocations constructor.
   *
   * @param \Composer\IO\IOInterface $io
   *   The Composer IO interface.
   * @param string $webRoot
   *   The web root of the project. This is either defined in the project root
   *   composer.json, or taken to be the project root by default.
   */
  public function __construct(
    protected IOInterface $io,
    protected string $webRoot,
  ) {
  }

  /**
   * Writes the location class.
   *
   * @return \Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldResult
   *   The result of the locations class file generation.
   */
  public function manageLocationsClass(): ScaffoldResult {
    $this->io->write("Drupal application root defined as {$this->webRoot}.");

    // Composer changes the current directory to the project root, even if it
    // run in a subdirectory.
    $absolute_project_root = getcwd();

    $absolute_app_root = $absolute_project_root . '/' . $this->webRoot;
    $absolute_app_root = realpath($absolute_app_root);

    $relative_app_root = '/' . trim($this->webRoot, '/');

    $class_php = LocationsClassTemplate::getLocationsClassCode($relative_app_root);

    $locations_class_directory = static::getLocationsClassDirectory($absolute_project_root, $absolute_app_root);
    if (!is_writable($locations_class_directory)) {
      throw new \Exception(sprintf("The directory %s is not writable.", $locations_class_directory));
    }

    $file_location = $locations_class_directory . '/DrupalLocation.php';

    $result = file_put_contents($file_location, $class_php);

    if ($result !== FALSE) {
      $this->io->write("Writing Drupal locations class to $file_location.");
    }
    else {
      $this->io->writeError("There was a problem writing the Drupal locations class to $file_location.");
    }

    $scaffold_file_path = new ScaffoldFilePath('locations', 'drupal/core', '[project-root]/DrupalLocation.php', $file_location);

    return new ScaffoldResult($scaffold_file_path, TRUE);
  }

  /**
   * Gets the directory to write the locations class to.
   *
   * @param string $absolute_project_root
   *   The absolute path to the Composer project root, without a trailing slash.
   * @param string $absolute_app_root
   *   The absolute path to the Drupal application root, without a trailing
   *   slash.
   *
   * @return string
   *   The absolute path of the directory to write to.
   */
  protected static function getLocationsClassDirectory(string $absolute_project_root, string $absolute_app_root): string {
    return $absolute_project_root;
  }

}
