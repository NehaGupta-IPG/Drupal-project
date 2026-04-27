<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\SymfonyConfigShim\Loader;

use Drupal\Core\DependencyInjection\SymfonyConfigShim\FileLocatorInterface;

/**
 * Stub for \Symfony\Component\Config\Loader\FileLoader.
 */
abstract class FileLoader {

  public function __construct(
    protected FileLocatorInterface $locator,
    protected ?string $env = NULL,
  ) {}

  /**
   * Required by upstream, but a no-op here.
   */
  public function setCurrentDir(string $dir): void {}

  /**
   * Loads a resource.
   */
  abstract public function load(mixed $resource, ?string $type = NULL): mixed;

  /**
   * Returns whether this class supports the given resource.
   */
  abstract public function supports(mixed $resource, ?string $type = NULL): bool;

}
