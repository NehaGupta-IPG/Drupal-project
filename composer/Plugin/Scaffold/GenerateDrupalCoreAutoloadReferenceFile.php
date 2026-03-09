<?php

namespace Drupal\Composer\Plugin\Scaffold;

use Composer\Util\Platform;
use Symfony\Component\Filesystem\Path;

/**
 * Generates an 'autoload.php' that includes the autoloader created by Composer.
 *
 * @internal
 */
final class GenerateDrupalCoreAutoloadReferenceFile extends GenerateAutoloadReferenceFileBase {

  /**
   * {@inheritdoc}
   */
  protected static function autoloadPath(string $package_name, string $web_root, string $drupal_core): ScaffoldFilePath {
    $rel_path = 'autoload.php';
    $dest_rel_path = Path::makeRelative($drupal_core, Platform::getCwd()) . '/' . $rel_path;
    $dest_full_path = $drupal_core . '/' . $rel_path;
    return new ScaffoldFilePath('autoload', $package_name, $dest_rel_path, $dest_full_path);
  }

}
