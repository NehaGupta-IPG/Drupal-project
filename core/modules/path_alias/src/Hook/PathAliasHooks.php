<?php

namespace Drupal\path_alias\Hook;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for path_alias.
 */
class PathAliasHooks {

  /**
   * Implements hook_language_fallback_candidates_OPERATION_alter().
   */
  #[Hook('language_fallback_candidates_path_alias_alter')]
  public function language_fallback_candidates_path_alias_alter(array &$candidates, array $context): void {
    // Only fall back to LanguageInterface::LANGCODE_NOT_SPECIFIED.
    $candidates = array_intersect_key($candidates, [
      $context['langcode'] => 1,
      LanguageInterface::LANGCODE_NOT_SPECIFIED => 1,
    ]);
  }

}
