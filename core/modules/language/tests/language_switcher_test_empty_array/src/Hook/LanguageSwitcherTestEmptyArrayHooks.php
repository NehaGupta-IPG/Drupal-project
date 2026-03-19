<?php

declare(strict_types=1);

namespace Drupal\language_switcher_test_empty_array\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for language_switcher_test_empty_array.
 */
class LanguageSwitcherTestEmptyArrayHooks {

  /**
   * Implements hook_language_switch_links_alter.
   */
  #[Hook('language_switch_links_alter')]
  public function languageSwitchLinksAlter(&$links, string $type, Url $url): void {
    // Return an empty array which will hide the language switcher block.
    $links = [];
  }

}
