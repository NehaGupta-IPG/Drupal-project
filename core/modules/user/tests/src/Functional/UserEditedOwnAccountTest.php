<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests user edited own account can still log in.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserEditedOwnAccountTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a user who edits their own account can still log in.
   */
  public function testUserEditedOwnAccount(): void {
    // Change account setting 'Who can register accounts?' to Administrators
    // only.
    $this->config('user.settings')->set('register', UserInterface::REGISTER_ADMINISTRATORS_ONLY)->save();

    // Create a new user account and log in.
    $account = $this->drupalCreateUser(['change own username']);
    $this->drupalLogin($account);

    // Change own username.
    $edit = [];
    $edit['name'] = $this->randomMachineName();
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->submitForm($edit, 'Save');

    // Log out.
    $this->drupalLogout();

    // Set the new name on the user account and attempt to log back in.
    $account->name = $edit['name'];
    $this->drupalLogin($account);

    // Attempt to change username to an email other than my own.
    $edit['name'] = $this->randomMachineName() . '@example.com';
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('An email address was provided as a username, but does not match the account email address.');
    $this->assertSession()->pageTextNotContains('The changes have been saved.');

    // Lookup user by name to make sure we didn't actually change the name.
    $accounts = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['name' => $edit['name']]);
    $this->assertTrue(empty($accounts), 'Username was not changed to email address other than my own.');

    // Change username to my email address.
    $edit['name'] = $account->getEmail();
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The changes have been saved.');

    // Test that 'verify_email_match' turned off allows emails that don't match.
    $this->config('user.settings')->set('verify_email_match', FALSE)->save();

    // Change username to random, non-matching email address.
    $edit['name'] = $this->randomMachineName() . '@example.com';
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The changes have been saved.');
  }

}
