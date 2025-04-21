<?php

namespace Drupal\olivero;

use Drupal\Core\Theme\StarterKitInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
   * Copies bundler files from core.
   *
   * @param string $dir
   *   The working directory of the template being generated.
   * @param string $machine_name
   *   The theme's machine name.
   */
  private static function getBuildFiles(string $dir, string $machine_name): void {
    // Copy & simplify package.json.
    $finder = new Finder();
    $finder->in(__DIR__ . '/../../../')->depth('== 0')->files()->name('package.json');
    if (count($finder) === 1) {
      foreach ($finder as $file) {
        $package_json = json_decode($file->getContents());

        $package_json->name = $machine_name;
        unset($package_json->description);

        foreach ($package_json->scripts as $key => $value) {
          if (!in_array($key, self::$scriptsToKeep)) {
            unset($package_json->scripts->$key);
          }
        }

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

        file_put_contents($dir . '/package.json', str_replace('    ', '  ', json_encode($package_json, JSON_PRETTY_PRINT)));
      }
    }

    $fs = new Filesystem();

    // Copy core/scripts/css
    $fs->mirror(__DIR__ . '/../../../scripts/css', $dir . '/scripts/css');

    // Copy .stylelintrc.json
    $fs->copy(__DIR__ . '/../../../.stylelintrc.json', $dir . '/.stylelintrc.json');
  }

  /**
   * {@inheritdoc}
   */
  public static function postProcess(string $working_dir, string $machine_name, string $theme_name): void {
    self::getBuildFiles($working_dir, $machine_name);
    rename("$working_dir/starterkit.md", "$working_dir/README.md");
  }

}
