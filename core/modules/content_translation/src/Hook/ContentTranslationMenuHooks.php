<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Menu hook implementations for content_translation.
 */
readonly class ContentTranslationMenuHooks {

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    // Clarify where translation settings are located.
    $links['language.content_settings_page']['title'] = new TranslatableMarkup('Content language and translation');
    $links['language.content_settings_page']['description'] = new TranslatableMarkup('Configure language and translation support for content.');
  }

}
