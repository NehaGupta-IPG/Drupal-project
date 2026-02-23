<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the user send element access on administration page.
 *
 * @group user
 */
class UserSendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the access to email notifications form element.
   */
  public function testSendWelcomeEmailAccess(): void {
    $admin_user = $this->drupalCreateUser(['administer users']);
    $this->drupalLogin($admin_user);

    // Create an active user without email.
    $name = $this->randomMachineName();
    $edit = [
      'name' => $name,
      'pass[pass1]' => $pass = $this->randomString(),
      'pass[pass2]' => $pass,
      'notify' => FALSE,
    ];
    $this->drupalGet('admin/people/create');
    $this->submitForm($edit, 'Create new account');
    $account = user_load_by_name($edit['name']);
    $this->drupalGet('user/' . $account->id() . '/edit');
    // Send button is not displayed.
    $this->assertSession()->responseNotContains('Send welcome message');

    // Create an active user with email.
    $test_user = $this->drupalCreateUser();
    $test_user->save();
    $this->drupalGet('user/' . $test_user->id() . '/edit');
    // Send button is displayed.
    $this->assertSession()->responseContains('Send welcome message');
  }

}
