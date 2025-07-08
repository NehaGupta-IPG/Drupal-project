<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test 'sub-admin' account with permission to edit some users but without 'administer users' permission.
 *
 * @group user
 */
class UserSubAdminTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user_access_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests create and cancel forms as 'sub-admin'.
   */
  public function testSubAdmin(): void {
    $user = $this->drupalCreateUser(['sub-admin']);
    $this->drupalLogin($user);

    // Test that the create user page has admin fields.
    $this->drupalGet('admin/people/create');
    $this->assertSession()->fieldExists("edit-name");
    $this->assertSession()->fieldExists("edit-notify");

    // Not 'status' or 'roles' as they require extra permission.
    $this->assertSession()->fieldNotExists("edit-status-0");
    $this->assertSession()->fieldNotExists("edit-role");

    // Test that create user gives an admin style message.
    $edit = [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass[pass1]' => $pass = $this->randomString(),
      'pass[pass2]' => $pass,
      'notify' => FALSE,
    ];
    $this->drupalGet('admin/people/create');
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->pageTextContains('Created a new user account for ' . $edit['name'] . '. No email has been sent.');

    // Test that the cancel user page has admin fields.
    $cancel_user = $this->createUser();
    $this->drupalGet('user/' . $cancel_user->id() . '/cancel');
    $this->assertSession()->responseContains('Are you sure you want to cancel the account ' . $cancel_user->getAccountName() . '?');
    $this->assertSession()->responseContains('Disable the account and keep its content.');

    // Test that cancel confirmation gives an admin style message.
    $this->submitForm([], 'Confirm');
    $this->assertSession()->pageTextContains('Account ' . $cancel_user->getAccountName() . ' has been disabled.');

    // Repeat with permission to select account cancellation method.
    $user
      ->addRole($this->drupalCreateRole(['select account cancellation method']))
      ->save();
    $cancel_user = $this->createUser();
    $this->drupalGet('user/' . $cancel_user->id() . '/cancel');
    $this->assertSession()->pageTextContains('Cancellation method');
  }

  /**
   * Cover user cancel configuration.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCancelAccount(): void {
    $admin_user = $this->drupalCreateUser(['administer users']);

    $config = $this->config('user.settings');
    $config->set('cancel_method_options.user_cancel_block', TRUE);
    $config->set('cancel_method_options.user_cancel_block_unpublish', TRUE);
    $config->set('cancel_method_options.user_cancel_reassign', FALSE);
    $config->set('cancel_method_options.user_cancel_delete', FALSE);
    $config->save();
    $this->drupalLogin($admin_user);

    // Test that the cancel user page do not show the disabled fields.
    $cancel_user = $this->createUser();
    $this->drupalGet('user/' . $cancel_user->id() . '/cancel');
    $this->assertSession()->responseContains('Are you sure you want to cancel the account ' . $cancel_user->getAccountName() . '?');
    $this->assertSession()->fieldExists('user_cancel_method[user_cancel_block]');
    $this->assertSession()->fieldExists('user_cancel_method[user_cancel_block_unpublish]');
    $this->assertSession()->fieldNotExists('user_cancel_method[user_cancel_reassign]');
    $this->assertSession()->fieldNotExists('user_cancel_method[user_cancel_delete]');
  }

  /**
   * Cover user cancellation validations.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testCancelAccountValidations(): void {
    $admin_user = $this->drupalCreateUser([
      'administer users',
      'administer account settings',
    ]);

    // Check validation user cancellation method cannot be a disabled option.
    $config = $this->config('user.settings');
    $config->set('cancel_method_options.user_cancel_block', TRUE);
    $config->set('cancel_method_options.user_cancel_block_unpublish', TRUE);
    $config->set('cancel_method_options.user_cancel_reassign', FALSE);
    $config->set('cancel_method_options.user_cancel_delete', FALSE);
    $config->set('cancel_method', 'user_cancel_block');
    $config->save();
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/people/accounts');
    $container = $this->assertSession()->elementExists('css', '#edit-user-cancel-method-options--wrapper');
    $this->assertSession()->fieldExists('user_cancel_method_options[user_cancel_block]')->uncheck();
    $this->assertSession()->checkboxNotChecked('Disable the account and keep its content.', $container);
    $this->assertSession()->checkboxChecked('Disable the account and unpublish its content.', $container);
    $this->assertSession()->checkboxNotChecked('Delete the account and make its content belong to the', $container);
    $this->assertSession()->checkboxNotChecked('Delete the account and its content. This action cannot be undone.', $container);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->responseContains('The default user cancellation method cannot be a disabled option.');

    // Check validation:
    // At least one user cancellation option should be enabled.
    $this->getSession()->getDriver()->uncheck('.//input[@name="user_cancel_method_options[user_cancel_block_unpublish]"]');
    $this->assertSession()->checkboxNotChecked('Disable the account and keep its content.', $container);
    $this->assertSession()->checkboxNotChecked('Disable the account and unpublish its content.', $container);
    $this->assertSession()->checkboxNotChecked('Delete the account and make its content belong to the', $container);
    $this->assertSession()->checkboxNotChecked('Delete the account and its content. This action cannot be undone.', $container);
    $this->submitForm([], 'Save configuration');
    $this->assertSession()->responseContains('At least one user cancellation option should be enabled.');

    $this->submitForm([
      'user_cancel_method_options[user_cancel_block]' => TRUE,
      'user_cancel_method_options[user_cancel_block_unpublish]' => TRUE,
    ], 'Save configuration');
    $this->assertSession()->responseContains('The configuration options have been saved.');
  }

}
