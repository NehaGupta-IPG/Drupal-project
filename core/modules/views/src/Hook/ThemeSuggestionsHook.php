<?php

namespace Drupal\views\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme suggestions for views.
 */
class ThemeSuggestionsHook {

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_views_view')]
  public function themeSuggestionsViewsView(array $variables): array {
    $suggestions = [];
    $view = $variables['view'];

    $suggestions[] = 'views_view__' . $view->id();
    $suggestions[] = 'views_view__' . $view->current_display;
    $suggestions[] = 'views_view__' . $view->id() . '__' . $view->current_display;

    return $suggestions;
  }

}
