<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests registration of user with roles set to null.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserRegistrationMultistepTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user_form_multistep_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests user registration form submission when roles are set to null.
   */
  public function testRegistrationWithRolesNull(): void {
    $config = $this->config('user.settings');
    // Allow registration by site visitors without administrator approval.
    $config->set('register', UserInterface::REGISTER_VISITORS)->save();
    $edit = [];
    $edit['name'] = $name = $this->randomMachineName();
    $edit['mail'] = $mail = $edit['name'] . '@example.com';
    $this->drupalGet('user/register');
    $this->submitForm($edit, 'Create new account');
    $this->assertSession()->statusCodeEquals(200);
  }

}
