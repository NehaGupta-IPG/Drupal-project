<?php

declare(strict_types=1);

namespace Drupal\Tests\user\FunctionalJavascript;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Database;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Ensure that password reset methods work as expected.
 *
 * @group user
 */
class UserPasswordResetTest extends WebDriverTestBase {

  use AssertMailTrait {
    getMails as drupalGetMails;
  }

  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * The user object to test password resetting.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'test_user_config'];

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
    $account = $this->drupalCreateUser(['access content']);

    // Activate user by logging in.
    $this->drupalLogin($account);

    $this->account = User::load($account->id());
    $this->account->pass_raw = $account->pass_raw;
    $this->drupalLogout();

    // Set the last login time that is used to generate the one-time link so
    // that it is definitely over a second ago.
    $account->login = \Drupal::time()->getRequestTime() - mt_rand(10, 100000);
    Database::getConnection()->update('users_field_data')
      ->fields(['login' => $account->getLastLoginTime()])
      ->condition('uid', $account->id())
      ->execute();
  }

  /**
   * Tests password reset functionality with an AJAX form.
   *
   * Make sure the ajax request from uploading a user picture does not
   * invalidate the reset token.
   */
  public function testUserPasswordResetWithAdditionalAjaxForm(): void {
    $this->drupalGet(Url::fromRoute('user.reset.form', ['uid' => $this->account->id()]));

    // Try to reset the password for an invalid account.
    $this->drupalGet('user/password');

    // Reset the password by username via the password reset page.
    $edit['name'] = $this->account->getAccountName();
    $this->submitForm($edit, 'Submit');

    $resetURL = $this->getResetUrl();
    $this->drupalGet($resetURL);

    // Login.
    $this->submitForm([], 'Log in');

    // Generate file.
    $image_file = current($this->drupalGetTestFiles('image'));
    $image_path = \Drupal::service('file_system')->realpath($image_file->uri);

    // Upload file.
    $this->getSession()->getPage()->attachFileToField('Picture', $image_path);
    $this->assertSession()->waitForButton('Remove');

    // Change the forgotten password.
    $password = \Drupal::service('password_generator')->generate();
    $edit = ['pass[pass1]' => $password, 'pass[pass2]' => $password];
    $this->submitForm($edit, 'Save');

    // Verify that the password reset session has been destroyed.
    $this->submitForm($edit, 'Save');
    // Password needed to make profile changes.
    $this->assertSession()->pageTextContains("Your current password is missing or incorrect; it's required to change the Password.");
  }

  /**
   * Retrieves password reset email and extracts the login link.
   */
  public function getResetUrl(): string {
    // Assume the most recent email.
    $_emails = $this->drupalGetMails();
    $email = end($_emails);
    $urls = [];
    preg_match('#.+user/reset/.+#', $email['body'], $urls);

    return $urls[0];
  }

  /**
   * Attempts to reset a password when an email address matches two accounts.
   */
  public function testUserPasswordResetDuplicateUsers(): void {
    $user_settings = $this->config('user.settings');

    // Don't require email validation for new accounts.
    $user_settings->set('verify_mail', FALSE)->save();

    // Don't require admin approval for new accounts.
    $user_settings->set('register', UserInterface::REGISTER_VISITORS)->save();
    // Create two users.
    $user_with_email = $this->drupalCreateUser();
    $user_with_name = $this->drupalCreateUser();

    // Change the second user's username to the same value as the first user's
    // email address.
    $user_with_name->name = $user_with_email->getEmail();
    $user_with_name->save();

    // Try and reset based on the duplicated email.
    $edit = [];
    $edit['name'] = $user_with_email->getEmail();
    $this->submitForm($edit, 'Submit');
    // There should be a field prompting the user to pick and account.
    $this->assertSession()->fieldExists('choose_account');
    // We should be sure to not expose another user's email to the user.
    $this->assertSession()->pageTextNotContains($user_with_name->getEmail(), "Duplicated user's email is not exposed to the other user.");

    // Select the account with the username matching the entered email.
    $edit = [];
    $edit['choose_account'] = Crypt::hashBase64(Settings::getHashSalt() . $user_with_email->id());
    $this->submitForm($edit, 'Submit');
    $this->assertSession()->pageTextContains('Further instructions have been sent to your e-mail address.');

    // Make sure that right user was sent a reset email.
    $this->assertEquals(count($this->drupalGetMails([
      'key' => 'password_reset',
      'to' => $user_with_email->getEmail(),
    ]
    )), 1, 'The right user was sent a password reset mail.');
    // Make sure that the other user was not sent an email.
    $this->assertEquals(count($this->drupalGetMails([
      'key' => 'password_reset',
      'to' => $user_with_name->getEmail(),
    ])), 0, 'The other user was not sent a password reset mail.');

    // If the user is logged in, they should not have to choose an account to
    // reset their password.
    $this->drupalLogin($user_with_name);
    $this->drupalGet('user/password');
    // There should not be a form element for name.
    $this->assertSession()->fieldNotExists('name');
    $this->submitForm([], 'Submit');
    // Make sure the user with the matching username was sent an email.
    $this->assertSession()->pageTextContains('Further instructions have been sent to your e-mail address.');
    $this->assertEquals(count($this->drupalGetMails(
      [
        'key' => 'password_reset',
        'to' => $user_with_name->getEmail(),
      ])), 1, 'The right user was sent a password reset mail when logged in.');
    // Make sure that the user with the matching
    // email address was not sent an email.
    // (An email was already sent to this user earlier.)
    $this->assertEquals(count($this->drupalGetMails(
      [
        'key' => 'password_reset',
        'to' => $user_with_email->getEmail(),
      ])), 1, 'The other user was not sent a password reset mail when logged in.');
  }

}
