<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\SymfonyConfigShim;

/**
 * Stub for \Symfony\Component\Config\FileLocator.
 *
 * @internal
 */
class FileLocator implements FileLocatorInterface {

  /**
   * {@inheritdoc}
   */
  public function locate(string $name, ?string $currentPath = NULL, bool $first = TRUE): string|array {
    return $name;
  }

}
