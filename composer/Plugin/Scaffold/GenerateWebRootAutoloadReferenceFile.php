<?php

namespace Drupal\Composer\Plugin\Scaffold;

/**
 * Generates an 'autoload.php' that includes the autoloader created by Composer.
 *
 * @internal
 */
final class GenerateWebRootAutoloadReferenceFile extends GenerateAutoloadReferenceFileBase {

  /**
   * {@inheritdoc}
   */
  protected static function autoloadPath(string $package_name, string $web_root, string $drupal_core): ScaffoldFilePath {
    $rel_path = 'autoload.php';
    $dest_rel_path = '[web-root]/' . $rel_path;
    $dest_full_path = $web_root . '/' . $rel_path;
    return new ScaffoldFilePath('autoload', $package_name, $dest_rel_path, $dest_full_path);
  }

}
