<?php

declare(strict_types=1);

namespace Drupal\importmap_test_theme\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the importmap_test_theme theme.
 */
class ImportMapAlterHooks {

  /**
   * Implements hook_importmap_alter().
   *
   * Resolves the 'importmap-test-foo' conflict by keeping the entry of the
   * theme's libraries-override, and unsetting the conflict, so that it is not
   * logged. Controlled by the 'importmap_test_theme.resolve_conflict' state
   * value, so that tests without it are not affected.
   */
  #[Hook('importmap_alter')]
  public function importMapAlter(array &$import_map, array &$conflicts): void {
    if (\Drupal::state()->get('importmap_test_theme.resolve_conflict')) {
      unset($conflicts['imports']['importmap-test-foo']);
    }
  }

}
