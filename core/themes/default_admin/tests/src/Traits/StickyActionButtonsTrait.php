<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Traits;

/**
 * Provides a method to set the sticky action buttons feature flag.
 *
 * This trait is meant to be used only by test classes. It relies on
 * \Drupal\Core\Test\FunctionalTestSetupTrait::writeSettings(), so the test
 * class using it has to provide that trait as well, which browser test base
 * classes already do.
 */
trait StickyActionButtonsTrait {

  /**
   * Sets the sticky action buttons feature flag in settings.php.
   *
   * @param string $value
   *   The value for core_admin_theme_use_sticky_action_buttons. One of:
   *   - 'never': No form gets sticky action buttons. This is the default when
   *     the setting is absent.
   *   - 'content_forms': Only content forms get sticky action buttons.
   *   - 'always': Every form gets sticky action buttons.
   *
   * @see \Drupal\default_admin\Hook\FormHooks::useStickyActionButtons()
   */
  protected function setStickyActionButtons(string $value): void {
    $this->writeSettings([
      'settings' => [
        'core_admin_theme_use_sticky_action_buttons' => (object) [
          'value' => $value,
          'required' => TRUE,
        ],
      ],
    ]);
  }

}
