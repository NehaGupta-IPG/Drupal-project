<?php

declare(strict_types=1);

namespace Drupal\user\Hook;

use Drupal\Core\EventSubscriber\ColorSchemeCookieSubscriber;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Hook implementations for the color scheme preference.
 */
final class ColorSchemeHooks {

  public function __construct(
    private readonly AccountInterface $currentUser,
    private readonly ColorSchemeCookieSubscriber $colorSchemeCookie,
  ) {}

  /**
   * Implements hook_user_update().
   *
   * The current user is built from the session at the start of the request, so
   * it still holds the color scheme the account had before this save. Hand the
   * saved value over, so that the cookie is written on the response to the
   * save and the next page already uses the new preference.
   */
  #[Hook('user_update')]
  public function userUpdate(UserInterface $account): void {
    if ((int) $account->id() === (int) $this->currentUser->id()) {
      $this->colorSchemeCookie->setColorScheme($account->getColorScheme());
    }
  }

}
