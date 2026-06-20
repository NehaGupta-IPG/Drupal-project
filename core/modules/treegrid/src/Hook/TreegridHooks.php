<?php

declare(strict_types=1);

namespace Drupal\treegrid\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hooks for the treegrid module.
 */
final class TreegridHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.treegrid') {
      $output = '';
      $output .= '<h2>' . $this->t('About') . '</h2>';
      $output .= '<p>' . $this->t('The Tree grid module provides a reusable, lazy-loaded, keyboard-navigable treegrid that other modules build scalable hierarchical administration screens on, such as the Menu tree UI module. It has no user-facing screens of its own. For more information, see the <a href=":documentation">online documentation for the Tree grid module</a>.', [
        ':documentation' => 'https://www.drupal.org/docs/core-modules-and-themes/core-modules/tree-grid-module',
      ]) . '</p>';
      return $output;
    }
    return NULL;
  }

}
