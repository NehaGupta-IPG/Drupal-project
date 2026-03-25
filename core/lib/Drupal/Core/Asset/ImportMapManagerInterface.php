<?php

declare(strict_types=1);

namespace Drupal\Core\Asset;

use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\Core\DestructableInterface;

/**
 * Defines an interface for an import map manager.
 */
interface ImportMapManagerInterface extends CacheCollectorInterface, DestructableInterface {

  /**
   * Gets all the import maps for given theme.
   *
   * @param string $theme
   *   Theme machine name.
   *
   * @return array
   *   Array of import maps with keys 'imports' and 'scopes'.
   */
  public function getImportMapForTheme(string $theme): array;

}
