<?php

declare(strict_types=1);

namespace Drupal\mailer_email_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Test email plugin methods.
 */
class EmailTestThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'mailer_email_test_html_body' => [
        'variables' => [
          'email' => NULL,
        ],
      ],
      'mailer_email_test_text_body' => [
        'variables' => [
          'email' => NULL,
        ],
      ],
    ];
  }

}
