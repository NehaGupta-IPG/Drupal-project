<?php

declare(strict_types=1);

namespace Drupal\views_test_data\Plugin\views\display_extender;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Attribute\ViewsDisplayExtender;

/**
 * Defines the third display extender test plugin.
 */
#[ViewsDisplayExtender(
  id: 'display_extender_test_3',
  title: new TranslatableMarkup('Display extender test number three'),
)]
class DisplayExtenderTest3 extends DisplayExtenderTest {

  /**
   * Whether to generate errors during validation.
   */
  private static bool $errors = FALSE;

  /**
   * Enable error generation for testing purposes.
   */
  public static function enableErrors(): void {
    static::$errors = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    return static::$errors ? ['Display extender test error.'] : [];
  }

}
