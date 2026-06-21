<?php

declare(strict_types=1);

namespace Drupal\treegrid_reorder\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hooks for the treegrid_reorder module.
 */
final class TreegridReorderHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.treegrid_reorder') {
      $output = '';
      $output .= '<h2>' . $this->t('About') . '</h2>';
      $output .= '<p>' . $this->t('The Tree grid reorder module adds drag-and-drop and keyboard reordering to <a href=":treegrid">Tree grid</a> screens. It provides the generic gesture handling, universal move validation, and an optional working copy; the consuming module supplies the domain rules (which items may move, depth limits) and the storage write. It has no user-facing screens of its own. For more information, see the <a href=":documentation">online documentation for the Tree grid reorder module</a>.', [
        ':treegrid' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/tree-grid-module',
        ':documentation' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/tree-grid-reorder-module',
      ]) . '</p>';
      return $output;
    }
    return NULL;
  }

}
