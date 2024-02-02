<?php

namespace Drupal\Tests\user\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UiHelperTrait;
use Drupal\user\Controller\MailChangeController;
use Drupal\user\Entity\User;

/**
 * Ensures that email change works as expected.
 *
 * @group user
 */
class UserMailChangeTest extends BrowserTestBase {

  use AssertMailTrait;
  use UiHelperTrait;

  /**
   * The user object to test password resetting for.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create a user.
    $this->account = $this->drupalCreateUser();
    $this->time = $this->container->get('datetime.time');
  }

  /**
   * Tests email change functionality.
   *
   * @dataProvider providerTestMailChange
   */
  public function testMailChange($with_password): void {
    $this->drupalLogin($this->account);

    // Ensure a time between the user last login and the time the account edit
    // is posted. A human cannot login, edit the account and post the changes
    // within the same second. But tests occasionally are running all steps in
    // the same timestamp, so that the mail change URL timestamp equals the user
    // last login timestamp. Later, in this test, when the user tries to reuse
    // the expired link, the test is still within the timestamp when the user
    // has logged in and a time difference cannot be experienced a because the
    // user last login time has seconds as granularity.
    sleep(1);

    // Change the user email address.
    $new_mail = 'foo@example.com';
    $edit = [
      'mail' => $new_mail,
      'current_pass' => $this->account->pass_raw,
    ];
    if ($with_password) {
      $new_password = \Drupal::service('password_generator')
        ->generate();
      $edit += [
        'pass[pass1]' => $new_password,
        'pass[pass2]' => $new_password,
      ];
    }

    $this->drupalGet($this->account->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check that the validation status message was displayed.
    $this->assertSession()->pageTextContains('You must confirm your email address. Further instructions have been sent to your new email address.');

    $user_mail = $this->config('user.mail');

    /** @var \Drupal\Core\Utility\Token $token_service */
    $token_service = $this->container->get('token');

    // Check that a notification email was sent.
    $this->assertMail('to', $this->account->getEmail());
    $subject = $token_service->replace($user_mail->get('mail_change_notification.subject'), ['user' => $this->account]);
    $this->assertMail('subject', $subject);

    // Check that a verification email was sent.
    $this->assertMailString('to', $new_mail, 2);
    $subject = $token_service->replace($user_mail->get('mail_change_verification.subject'), ['user' => $this->account]);
    $this->assertMailString('subject', $subject, 2);

    $sent_mail_change_url = $this->extractUrlFromMail('user_mail_change_verification');

    // Check that the email was updated.
    $this->drupalGet($sent_mail_change_url);
    $this->assertSession()->responseContains(new FormattableMarkup('Your email address has been changed to %mail.', ['%mail' => $new_mail]));

    // Check that the change email URL is not cached and expires after first
    // use.
    $this->drupalGet($sent_mail_change_url);
    $this->assertNull($this->getSession()->getResponseHeader('X-Drupal-Cache'));
    $this->assertSession()->responseContains('You have tried to use an email address change link that has either been used or is no longer valid. Visit your account and change your email again.');

    // Check that the user mail has been changed.
    $this->assertSame(User::load($this->account->id())->getEmail(), $new_mail);
  }

  /**
   * Provides data for testMailChange.
   */
  public function providerTestMailChange(): array {
    return [
      'no_password change' => [
        FALSE,
      ],
      'with password change' => [
        TRUE,
      ],
    ];
  }

  /**
   * Tests email change functionality for a user without am email address.
   *
   * Drupal allows accounts without an email when the account is created by an
   * administrator. Note that, changing an non-empty email to an empty one, is
   * not allowed.
   */
  public function testMailChangeForUserWithEmptyEmail(): void {
    // Simulate a user without an email address.
    $this->account->setEmail(NULL)->save();

    $this->drupalLogin($this->account);
    $edit = [
      'mail' => 'foo@example.com',
      'current_pass' => $this->account->pass_raw,
    ];
    $this->drupalGet($this->account->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check that the validation status message was displayed.
    $this->assertSession()->pageTextContains('You must confirm your email address. Further instructions have been sent to your new email address.');

    // Check that only the verification email was sent.
    $this->assertCount(1, $this->getMails());
    $this->assertNotEmpty($this->getMails(['id' => 'user_mail_change_verification']));
  }

  /**
   * Tests email change functionality when email change verification is off.
   */
  public function testMailChangeNoVerification(): void {
    // Disable email change verification.
    $this->config('user.settings')
      ->set('notify.mail_change_verification', FALSE)
      ->save();
    $this->drupalLogin($this->account);

    // Change the user email address.
    $new_mail = 'foo@example.com';
    $edit = [
      'mail' => $new_mail,
      'current_pass' => $this->account->pass_raw,
    ];
    $this->drupalGet($this->account->toUrl('edit-form'));
    $this->submitForm($edit, 'Save');

    // Check that the validation status message was not displayed.
    $this->assertSession()->pageTextNotContains('You must confirm your email address. Further instructions have been sent to your new email address.');

    // Check that no email was sent to the old or to the new address.
    $this->assertEmpty($this->getMails());

    // Check that the user's email was changed.
    $this->assertSame($new_mail, User::load($this->account->id())->getEmail());
  }

  /**
   * Tests change of email for blocked users.
   */
  public function testBlockedUser(): void {
    $timestamp = $this->time->getRequestTime() - 1;
    $this->account->block()->save();
    $this->drupalGet(MailChangeController::getUrl($this->account, [], $timestamp)->getInternalPath());
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests change of email for expired timestamp.
   */
  public function testExpiredTimestamp(): void {
    // Set the expired timestamp to the previous day minus one second.
    $timestamp = $this->time->getRequestTime() - (86401);
    $this->drupalGet(MailChangeController::getUrl($this->account, [], $timestamp)->getInternalPath());
    $this->assertSession()->responseContains('You have tried to use an email address change link that has expired. Visit your account and change your email again.');
  }

  /**
   * Tests change of email when another user is logged in.
   */
  public function testOtherUserLoggedIn(): void {
    $timestamp = $this->time->getRequestTime() - 1;
    // Create other account and login with it.
    $current_account = $this->drupalCreateUser();
    $this->drupalLogin($current_account);
    // Try to change the email for the first account when the other account is
    // logged in.
    $new_mail = 'foo@example.com';
    $this->account->setEmail($new_mail);
    $options['new_mail'] = $new_mail;
    $path = MailChangeController::getUrl($this->account, $options, $timestamp)->getInternalPath();
    $this->drupalGet($path);
    $this->assertSession()->responseContains(new FormattableMarkup('You are currently logged in as %user, and are attempting to confirm an email address change for another account. <a href=":logout">Log out</a> and try using the link again.', ['%user' => $current_account->getAccountName(), ':logout' => Url::fromRoute('user.logout')->toString()]));

    // Retry as anonymous.
    $this->drupalLogout();
    $this->drupalGet($path);
    $this->assertSession()->responseContains(new FormattableMarkup('Your email address has been changed to %mail.', ['%mail' => $new_mail]));
    // Confirm the user was not logged in.
    $this->assertFalse($this->drupalUserIsLoggedIn($this->account));
  }

  /**
   * Tests change of email for timestamp in the future.
   */
  public function testFutureTimestamp(): void {
    // Set the timestamp to 1 hour in the future.
    $timestamp = $this->time->getRequestTime() + 3600;
    $this->drupalGet(MailChangeController::getUrl($this->account, [], $timestamp)->getInternalPath());
    $this->assertSession()->responseContains('You have tried to use an email address change link that has either been used or is no longer valid. Visit your account and change your email again.');
  }

  /**
   * Tests change of email with the wrong hash.
   */
  public function testWrongHash(): void {
    $timestamp = $this->time->getRequestTime() - 1;
    // Generate the hash for other user.
    $other_account = $this->drupalCreateUser();
    $hash = user_pass_rehash($other_account, $timestamp);
    $this->drupalGet(MailChangeController::getUrl($this->account, [], $timestamp, $hash)->getInternalPath());
    $this->assertSession()->responseContains('You have tried to use an email address change link that has either been used or is no longer valid. Visit your account and change your email again.');
  }

  /**
   * Test that the link must contain the requested email address.
   */
  public function testWrongEmail(): void {
    $timestamp = $this->time->getRequestTime() - 1;
    // Can't use MailChangeController here as its ::getUrl method uses the
    // stored email address.
    $langcode = $this->account->getPreferredLangcode();
    $url_options = ['absolute' => TRUE, 'language' => \Drupal::service('language_manager')->getLanguage($langcode)];
    $url = Url::fromRoute('user.mail_change', [
      'user' => $this->account->id(),
      'timestamp' => $timestamp,
      'new_mail' => 'foo@example.com',
      'hash' => user_pass_rehash($this->account, $timestamp),
    ], $url_options);
    $this->drupalGet($url);
    $this->assertSession()->responseContains('You have tried to use an email address change link that has either been used or is no longer valid. Visit your account and change your email again.');
  }

  /**
   * Retrieves the change email and extracts the link.
   *
   * @param string $mail_id
   *   Unique mail ID.
   *
   * @return string
   *   A URL.
   */
  protected function extractUrlFromMail($mail_id): string {
    // Assume the most recent email.
    $email = $this->getMails(['id' => $mail_id]);
    $email = end($email);
    preg_match('#.+user\/mail\-change\/.+#', $email['body'], $urls);
    return $urls[0];
  }

}
