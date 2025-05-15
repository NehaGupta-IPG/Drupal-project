<?php

declare(strict_types=1);

namespace Drupal\user_hooks_test\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Contains hook implementations.
 */
class UserHooksTest {

  /**
   * Alters the username.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $name
   *   The username that is displayed for a user.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The object on which the operation is being performed.
   */
  #[Hook('user_format_name_alter')]
  public function userFormatNameAlter(&$name, AccountInterface $account): void {
    if (\Drupal::keyValue('user_hooks_test')->get('user_format_name_alter', FALSE)) {
      if (\Drupal::keyValue('user_hooks_test')->get('user_format_name_alter_safe', FALSE)) {
        $name = new FormattableMarkup('<em>@uid</em>', ['@uid' => $account->id()]);
      }
      else {
        $name = '<em>' . $account->id() . '</em>';
      }
    }
  }

  /**
   * Alters the mail language.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account of a user used in email.
   * @param string $language
   *   The language code used for user mail.
   */
  #[Hook('user_mail_language_alter')]
  public function userMailLanguageAlter(AccountInterface $account, string &$language): void {
    if (\Drupal::state()->get('user_hooks_test_user_mail_language_alter', TRUE)) {
      $language = 'fr';
    }
  }

}
