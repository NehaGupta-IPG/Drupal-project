<?php

declare(strict_types=1);

namespace Drupal\mailer\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for mailer.
 */
class MailerThemeHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'email' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessEmail',
      ],
    ];
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_email')]
  public function themeSuggestionsEmail(array $variables): array {
    [
      'module' => $module,
      'key' => $key,
      'langcode' => $langcode,
    ] = $variables['elements']['#props'];

    $suggestions = [];
    $suggestions[] = 'email__' . $langcode;
    $suggestions[] = 'email__' . $module;
    $suggestions[] = 'email__' . $module . '__' . $langcode;
    $suggestions[] = 'email__' . $module . '__' . $key;
    $suggestions[] = 'email__' . $module . '__' . $key . '__' . $langcode;
    return $suggestions;
  }

  /**
   * Prepares variables for email templates.
   *
   * Default template: email.html.twig.
   */
  public static function preprocessEmail(&$variables): void {
    // Copy parameters and derived data prepared by email controller and email
    // renderer.
    foreach (array_keys($variables['elements']['#props']) as $key) {
      if (!in_array($key, ['elements', 'content'], TRUE)) {
        $variables[$key] = $variables['elements']['#props'][$key];
      }
    }

    // Copy children into contents slot.
    foreach (Element::children($variables['elements']) as $key) {
      $variables['content'][$key] = $variables['elements'][$key];
    }
  }

}
