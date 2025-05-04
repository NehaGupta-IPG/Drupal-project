<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

/**
 * Container discovery.
 *
 * Creates a discovery object which will be cached, then unpacked before the
 * rest of the container definitions are unserialized, as the container
 * definitions may contain instances or references defined by extensions.
 *
 * @phpstan-import-type ContainerDefinition from \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper
 */
final class ContainerDiscovery {

  /**
   * Constructs a new Container Discovery object.
   *
   * @param array<string, string> $containerNamespaces
   *   An array of directory paths relative to app-root, keyed by PHP namespace.
   *   E.g:
   *   @code
   *   ['Drupal\system' => 'core/modules/system/src'];
   *   @endcode
   */
  private function __construct(
    private array $containerNamespaces
  )
  {
  }

  /**
   * Get container namespaces.
   *
   * @return array<string, string>
   *   The container namespaces.
   */
  public function getContainerNamespaces(): array {
    return $this->containerNamespaces;
  }

  /**
   * Create a discovery object from a primitive container definition.
   *
   * @param ContainerDefinition $containerDefinition
   *   The container definition to cache.
   */
  public static function fromContainerDefinition(array $containerDefinition): static {
    return new static(
      $containerDefinition['parameters']['container.namespaces'] ?? [],
    );
  }

}
