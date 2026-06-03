<?php

declare(strict_types=1);

namespace Drupal\Core\Discovery;

/**
 * Scans namespaces for instantiable classes carrying a given PHP attribute.
 *
 * This provides file-walking for attribute discovery: given a set of
 * namespaces (typically the 'container.namespaces' parameter), an optional
 * subdirectory, and an attribute class or interface to look for, it yields a
 * reflection object for every concrete class whose source references that
 * attribute. Consumers are then free to read whichever attributes they care
 * about from the reflection and build their own definitions from them.
 */
class AttributeScanner {

  /**
   * Constructs an AttributeScanner object.
   *
   * @param \Traversable<string, string|array<int, string>> $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look in.
   */
  public function __construct(
    protected readonly \Traversable $namespaces,
  ) {}

  /**
   * Scans for instantiable classes referencing an attribute.
   *
   * @param class-string $attributeName
   *   The attribute class or interface to look for. Both the class itself and
   *   any subclass or implementation are matched.
   * @param string $subdir
   *   (optional) The subdirectory of each namespace root to scan, for example
   *   'Controller' or 'Plugin/Block'. Defaults to scanning the full namespace.
   *
   * @return iterable<\ReflectionClass<object>>
   *   A generator yielding a reflection object for every concrete, loadable
   *   class whose source references the attribute.
   */
  public function scan(string $attributeName, string $subdir = ''): iterable {
    $markers = $this->getMarkers($attributeName);
    foreach ($this->namespaces as $namespace => $directories) {
      foreach ((array) $directories as $directory) {
        $scanNamespace = $namespace;
        if ($subdir !== '') {
          $directory .= '/' . $subdir;
          $scanNamespace .= '\\' . str_replace('/', '\\', $subdir);
        }
        if (!is_dir($directory)) {
          continue;
        }
        $iterator = new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileinfo) {
          assert($fileinfo instanceof \SplFileInfo);
          if ($fileinfo->getExtension() !== 'php') {
            continue;
          }
          // Filter files whose source does not reference the attribute, so that
          // the majority of classes are never reflected.
          $contents = file_get_contents($fileinfo->getPathname());
          $matched = FALSE;
          foreach ($markers as $marker) {
            if (str_contains($contents, $marker)) {
              $matched = TRUE;
              break;
            }
          }
          if (!$matched) {
            continue;
          }

          $subPath = $iterator->getSubIterator()->getSubPath();
          $subPath = $subPath ? str_replace(DIRECTORY_SEPARATOR, '\\', $subPath) . '\\' : '';
          $class = $scanNamespace . '\\' . $subPath . $fileinfo->getBasename('.php');

          // A class may reference classes, interfaces or traits provided by
          // modules that are not installed, in which case reflection throws an
          // error. This is unavoidable with optional dependencies, so silently
          // skip such a class; it will be found once its dependency is enabled.
          try {
            if (!class_exists($class)) {
              continue;
            }
          }
          catch (\Error) {
            continue;
          }

          $reflection = new \ReflectionClass($class);
          // The attribute marks something as callable by the system, which
          // requires the class to be instantiable.
          if ($reflection->isAbstract()) {
            continue;
          }

          yield $reflection;
        }
      }
    }
  }

  /**
   * Derives the markers used to filter files for an attribute.
   *
   * Two markers are returned, mirroring the two realistic ways an attribute is
   * referenced in source:
   * - '#[ShortName' catches inline usage (`#[Route(`) and aliasing of the
   *   attribute itself (`#[RouteFoo` still contains '#[Route').
   * - The fully qualified class name catches a `use ... as Alias;` import
   *   (where the short name never appears) and fully qualified inline usage
   *   (`#[\Symfony\...\Route(`).
   *
   * @param class-string $attributeName
   *   The attribute class or interface name.
   *
   * @return list<string>
   *   The markers to search for in file contents.
   */
  protected function getMarkers(string $attributeName): array {
    $shortName = substr(strrchr('\\' . $attributeName, '\\'), 1);
    return ['#[' . $shortName, $attributeName];
  }

}
