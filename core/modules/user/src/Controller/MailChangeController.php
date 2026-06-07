<?php

namespace Drupal\user\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a controller for email change routes.
 */
class MailChangeController extends ControllerBase {

  public function __construct(protected UserFloodControlInterface $flood, protected TimeInterface $time) {}

  /**
   * Returns the user mail change page.
   *
   * This controller must return a redirect response. This is to prevent
   * disclosure of an email change link via a referrer header.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account requesting an email change.
   * @param string $new_mail_hash
   *   Hash of the new email address.
   * @param int $timestamp
   *   The timestamp when the hash was created.
   * @param string $hash
   *   Unique hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   An HTTP redirect response.
   */
  public function page(UserInterface $user, string $new_mail_hash, int $timestamp, string $hash): RedirectResponse {
    $messenger = $this->messenger();
    $flood_config = $this->config('user.flood');
    if (!$this->flood->isAllowed('user.email_change_ip', $flood_config->get('ip_limit'), $flood_config->get('ip_window'))) {
      $messenger->addError($this->t('Too many email change requests from your IP address. It is temporarily blocked. Try again later or contact the site administrator.'));
      return $this->redirect('<front>');
    }
    $this->flood->register('user.email_change_ip', $flood_config->get('ip_window'));

    $timeout = $this->config('user.settings')->get('mail_change_timeout');
    /** @var \Drupal\Core\Session\AccountProxyInterface $current_user */
    $current_user = $this->currentUser();
    $request_time = $this->time->getRequestTime();

    // Another user is authenticated.
    if ($current_user->isAuthenticated() && ((int) $current_user->id() !== (int) $user->id())) {
      $arguments = [
        '%user' => $current_user->getAccountName(),
        ':logout' => Url::fromRoute('user.logout')->toString(),
      ];
      $messenger->addError($this->t('You are currently logged in as %user, and are attempting to confirm an email address change for another account. <a href=":logout">Log out</a> and try using the link again.', $arguments));
      return $this->redirect('<front>');
    }

    // The link has expired.
    if ($request_time - $timestamp > $timeout) {
      $messenger->addError($this->t('You have tried to use an email address change link that has expired. Visit your account and change your email again.'));
      return $this->redirect('<front>');
    }

    // Register flood events based on the UID only, so they apply for any IP
    // address. This allows them to be cleared on successful reset from any IP.
    $identifier = $user->id();
    if (!$this->flood->isAllowed('user.email_change_user', $flood_config->get('user_limit'), $flood_config->get('user_window'), $identifier)) {
      return $this->redirect('<front>');
    }
    $this->flood->register('user.email_change_user', $flood_config->get('user_window'), $identifier);

    // The link is valid.
    $new_mail = \Drupal::service('user.data')->get('user', $user->id(), 'email_change:' . $new_mail_hash);
    if ($timestamp <= $request_time && $timestamp >= $user->getLastLoginTime() && hash_equals($hash, user_pass_rehash($user, $timestamp, $new_mail))) {
      // Save the new email and also refresh the last login time so that this
      // email change link is expired.
      $user->setEmail($new_mail)->setLastLoginTime($request_time)->save();
      $arguments = ['%mail' => $new_mail];
      $messenger->addStatus($this->t('Your email address has been changed to %mail.', $arguments));
      $this->flood->clear('user.email_change_user', $user->id());
      return $this->redirect('<front>');
    }

    // The link is not valid. The timestamp from the link may be in the future
    // or the user registered a new login in the meantime or the hash is not
    // valid.
    $messenger->addError($this->t('You have tried to use an email address change link that has either been used or is no longer valid. Visit your account and change your email again.'));

    return $this->redirect('<front>');
  }

  /**
   * Checks access to the change email URL.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account requesting an email change.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   An access result.
   */
  public function access(UserInterface $user): AccessResultInterface {
    return AccessResult::allowedIf($user->isActive())->addCacheableDependency($user);
  }

  /**
   * Generates a unique URL for a one time email change confirmation.
   *
   * @param \Drupal\user\UserInterface $account
   *   An object containing the user account.
   * @param array $options
   *   (optional) A keyed array of settings. Supported options are:
   *   - langcode: A language code to be used when generating locale-sensitive
   *     URLs. If not specified, the user's preferred language is used.
   *   - new_mail: The new user email when in the process of changing the
   *     account email address.
   * @param int $timestamp
   *   (optional) The timestamp to use for creating the hash. Defaults to the
   *   current request time.
   * @param string $hash
   *   (optional) Unique hash. If not defined, the hash is computed based on the
   *   account data, the options array and the timestamp.
   *
   * @return \Drupal\Core\Url
   *   A unique URL that provides a one-time email change confirmation.
   */
  public static function getUrl(UserInterface $account, array $options = [], $timestamp = NULL, $hash = NULL): Url {
    $timestamp = $timestamp ?: \Drupal::time()->getRequestTime();
    $langcode = $options['langcode'] ?? $account->getPreferredLangcode();
    $new_mail = $options['new_mail'] ?? '';
    $hash = empty($hash) ? user_pass_rehash($account, $timestamp, $new_mail) : $hash;
    $url_options = [
      'absolute' => TRUE,
      'language' => \Drupal::service('language_manager')->getLanguage($langcode),
    ];

    // Create a hash of the the new mail address and save in user.data.
    $new_mail_hash = Crypt::hmacBase64($new_mail, \Drupal::service('private_key')->get() . Settings::getHashSalt());
    \Drupal::service('user.data')->set('user', $account->id(), 'email_change:' . $new_mail_hash, $new_mail);

    return Url::fromRoute('user.mail_change', [
      'user' => $account->id(),
      'timestamp' => $timestamp,
      'new_mail_hash' => $new_mail_hash,
      'hash' => $hash,
    ], $url_options);
  }

}
