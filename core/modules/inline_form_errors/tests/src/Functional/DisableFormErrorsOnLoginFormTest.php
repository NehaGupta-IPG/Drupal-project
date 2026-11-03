<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_form_errors\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the login form does not display inline form errors when login fails.
 */
#[Group('inline_form_errors')]
#[RunTestsInSeparateProcesses]
class DisableFormErrorsOnLoginFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'inline_form_errors',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and login a user.
    $account = $this->drupalCreateUser([
      'access content',
      'access administration pages',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests that failed logins do not result in inline error messages.
   */
  public function testFailedLoginErrors(): void {
    $this->drupalGet('user/login');
  }

}
