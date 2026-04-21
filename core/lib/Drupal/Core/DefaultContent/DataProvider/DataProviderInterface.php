<?php

declare(strict_types=1);

namespace Drupal\Core\DefaultContent\DataProvider;

/**
 * Provides content entity data for import.
 *
 * @internal
 *   This API is experimental.
 */
interface DataProviderInterface {

  /**
   * Returns the content entity data to import, keyed by UUID.
   *
   * @return array<string, array<mixed>>
   *   The entity data arrays, keyed by entity UUID.
   */
  public function getData(): array;

}
