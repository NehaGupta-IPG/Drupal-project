<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Provides shared hook collection functionality.
 *
 * @internal
 */
abstract class HookCollectorBase {

  /**
   * Gets the files that can contain hook implementations.
   *
   * @param string $dir
   *   The extension directory.
   * @param list<string> $procedural_files
   *   The root-level procedural hook implementation files.
   *
   * @return \Generator<\SplFileInfo>
   *   The procedural hook files and PHP files in src/Hook.
   */
  protected function getHookFileIterator(string $dir, array $procedural_files): \Generator {
    foreach ($procedural_files as $filename) {
      $filename = "$dir/$filename";
      if (is_file($filename)) {
        yield new \SplFileInfo($filename);
      }
    }

    $hook_directory = "$dir/src/Hook";
    if (!is_dir($hook_directory)) {
      return;
    }
    $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::FOLLOW_SYMLINKS;
    $iterator = new \RecursiveDirectoryIterator($hook_directory, $flags);
    $iterator = new \RecursiveCallbackFilterIterator($iterator, static fn (\SplFileInfo $fileinfo): bool => $fileinfo->isDir() || $fileinfo->getExtension() === 'php');
    foreach (new \RecursiveIteratorIterator($iterator) as $fileinfo) {
      yield $fileinfo;
    }
  }

  /**
   * Registers hook implementation services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param array<string, array<string, string>> $implementations_by_hook
   *   Implementations keyed by hook name and identifier.
   */
  protected static function registerHookServices(ContainerBuilder $container, array $implementations_by_hook): void {
    $classes_map = [];
    foreach ($implementations_by_hook as $hook_implementations) {
      foreach (array_keys($hook_implementations) as $identifier) {
        $parts = explode('::', $identifier, 2);
        if (isset($parts[1])) {
          $classes_map[$parts[0]] = TRUE;
        }
      }
    }

    foreach (array_keys($classes_map) as $class) {
      if (!$container->hasDefinition($class)) {
        $container
          ->register($class, $class)
          ->setAutowired(TRUE);
      }
    }
  }

}
