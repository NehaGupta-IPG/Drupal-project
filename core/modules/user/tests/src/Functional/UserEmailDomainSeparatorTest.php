<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\EmailTestDataTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests normalized domain separators throughout account form workflows.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserEmailDomainSeparatorTest extends BrowserTestBase {

  use AssertMailTrait;
  use EmailTestDataTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS)
      ->set('verify_mail', TRUE)
      ->save();
  }

  /**
   * Tests registration, normalized persistence and the welcome recipient.
   */
  #[DataProvider('normalizedDomainMailboxProvider')]
  public function testRegistration(string $mailbox, string $normalized): void {
    $name = $this->randomMachineName();
    $this->drupalGet('user/register');
    $this->submitForm(['name' => $name, 'mail' => $mailbox], 'Create new account');
    $this->assertSession()->pageTextContains('A welcome message with further instructions has been sent to your email address.');
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache();
    $accounts = $storage->loadByProperties(['name' => $name]);
    $this->assertCount(1, $accounts);
    $this->assertSame($normalized, reset($accounts)->getEmail());
    $mails = $this->getMails(['id' => 'user_register_no_approval_required']);
    $this->assertCount(1, $mails);
    $this->assertSame($normalized, $mails[0]['to']);
  }

  /**
   * Tests changing an account email address through its edit form.
   */
  #[DataProvider('normalizedDomainMailboxProvider')]
  public function testEmailChange(string $mailbox, string $normalized): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->submitForm(['mail' => $mailbox, 'current_pass' => $account->passRaw], 'Save');
    $this->assertSession()->pageTextContains('The changes have been saved.');
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache();
    $this->assertSame($normalized, $storage->load($account->id())->getEmail());
  }

  /**
   * Tests rejecting equivalent registration input as an existing mailbox.
   */
  #[DataProvider('normalizedDomainMailboxProvider')]
  public function testDuplicateRegistration(string $mailbox, string $normalized): void {
    $this->drupalCreateUser([], NULL, FALSE, ['mail' => $normalized]);
    $name = $this->randomMachineName();
    $this->drupalGet('user/register');
    $this->submitForm(['name' => $name, 'mail' => $mailbox], 'Create new account');
    $this->assertSession()->pageTextNotContains('is not valid');
    $this->assertSession()->pageTextContains('is already taken.');
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $this->assertCount(0, $storage->loadByProperties(['name' => $name]));
    $this->assertCount(0, $this->getMails(['id' => 'user_register_no_approval_required']));
  }

  /**
   * Tests lookup input and the account selected for password recovery.
   */
  #[DataProvider('normalizedDomainMailboxProvider')]
  public function testPasswordRecovery(string $mailbox, string $normalized): void {
    $account = $this->drupalCreateUser([], NULL, FALSE, ['mail' => $normalized]);
    // A different local part is a negative control for recipient selection.
    $other = $this->drupalCreateUser([], NULL, FALSE, ['mail' => 'other@例子.测试']);
    $this->drupalGet('user/password');
    $this->submitForm(['name' => $mailbox], 'Submit');
    $mails = $this->getMails(['id' => 'user_password_reset']);
    $this->assertCount(1, $mails);
    $this->assertSame($normalized, $mails[0]['to']);
    $this->assertStringContainsString('/user/reset/' . $account->id() . '/', $mails[0]['body']);
    $this->assertStringNotContainsString('/user/reset/' . $other->id() . '/', $mails[0]['body']);
  }

}
