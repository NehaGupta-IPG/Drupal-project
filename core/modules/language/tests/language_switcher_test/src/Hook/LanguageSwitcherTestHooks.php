<?php

declare(strict_types=1);

namespace Drupal\language_switcher_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for language_switcher_test.
 */
class LanguageSwitcherTestHooks {

  /**
   * Implements hook_language_switch_links_alter.
   */
  #[Hook('language_switch_links_alter')]
  public function languageSwitchLinksAlter(&$links, string $type, Url $url): void {
    // Return NULL which will hide the language switcher block.
    $links = NULL;
  }

}
