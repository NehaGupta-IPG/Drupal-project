<?php

namespace Drupal\olivero;

use Drupal\Core\Theme\StarterKitInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Interacts with theme generation.
 */
final class StarterKit implements StarterKitInterface {

  /**
   * Array of scripts to keep from core package.json.
   *
   * @var array
   */
  private static $scriptsToKeep = [
    'build:css',
    'watch:css',
  ];

  /**
   * Array of dependencies as pattern strings to keep from package.json.
   *
   * @var array
   */
  private static $depsToKeep = [
    "/^chokidar$/",
    "/^glob$/",
    "/^minimist$/",
    "/^postcss.*/",
    "/^prettier$/",
    "/^stylelint.*/",
  ];

  /**
   * {@inheritdoc}
   */
  public static function postProcess(string $working_dir, string $machine_name, string $theme_name): void {

    // Duplicate & simplify core's package.json.
    $path = __DIR__ . '/../../../package.json';
    if (file_exists($path)) {
      // Convert JSON to PHP object.
      $package_json = json_decode(file_get_contents($path));

      // Update package name & description.
      $package_json->name = $machine_name;
      unset($package_json->description);

      // Remove unwanted core scripts.
      foreach ($package_json->scripts as $key => $value) {
        if (!in_array($key, self::$scriptsToKeep)) {
          unset($package_json->scripts->$key);
        }
      }

      // Remove unwanted core dependencies.
      foreach ($package_json->devDependencies as $dep => $version) {
        $keep = FALSE;
        foreach (self::$depsToKeep as $dep_pattern) {
          if (preg_match($dep_pattern, $dep)) {
            $keep = TRUE;
          }
        }
        if (!$keep) {
          unset($package_json->devDependencies->$dep);
        }
      }

      // Convert PHP Object back to json.
      $package_json = str_replace('    ', '  ', json_encode($package_json, JSON_PRETTY_PRINT));

      file_put_contents($working_dir . '/package.json', $package_json);
    }

    $fs = new Filesystem();

    // Copy core/scripts/css
    $fs->mirror(__DIR__ . '/../../../scripts/css', $working_dir . '/scripts/css');

    // Copy .stylelintrc.json
    $fs->copy(__DIR__ . '/../../../.stylelintrc.json', $working_dir . '/.stylelintrc.json');

    rename("$working_dir/starterkit.md", "$working_dir/README.md");
  }

}
