<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests user account cancellation with a custom method.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserCancelCustomMethodTest extends BrowserTestBase {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user_cancel_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests user cancellation with a custom method.
   */
  public function testUserCancelCustomMethod(): void {
    $account = $this->createUser([
      'cancel account',
      'select account cancellation method',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet($account->toUrl('edit-form'));
    $page = $this->getSession()->getPage();
    $page->clickLink('Cancel account');

    // Chose the custom cancellation method.
    $page->selectFieldOption('user_cancel_method', 'user_cancel_test');
    $page->pressButton('Confirm');

    $this->clickConfirmationLinkFomMail();

    // Check that the custom user cancellation has been executed but not the
    // Drupal core cancellation.
    // @see \Drupal\user_cancel_test\Plugin\user\CancelMethod\UserCancelTest::cancel()
    // @see \Drupal\user\AccountCancellation::cancelAccount()
    $this->assertSession()->pageTextContains('Custom user cancel method executed.');
    $this->assertSession()->pageTextNotContains("{$account->getDisplayName()} has been disabled.");
  }

  /**
   * Clicks on the confirmation link sent by email.
   */
  protected function clickConfirmationLinkFomMail(): void {
    $mails = $this->getMails();
    $mail = reset($mails);
    preg_match('#http.*#', $mail['body'], $found);
    $this->drupalGet($found[0]);
  }

}
