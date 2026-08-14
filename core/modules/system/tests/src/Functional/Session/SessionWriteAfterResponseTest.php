<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Session;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests session access after the response has been sent.
 */
#[Group('Session')]
class SessionWriteAfterResponseTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['session_test', 'dblog'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that a session write after the response was sent does not fail.
   *
   * Destructable services run at the end of DrupalKernel::terminate(), when the
   * headers have already been flushed and the session has been closed. Starting
   * a PHP session is impossible then, so the write must be discarded instead of
   * raising a RuntimeException.
   *
   * @legacy-covers \Drupal\Core\Session\SessionManager::startNow
   */
  public function testSessionWriteAfterResponseSent(): void {
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    $this->drupalGet('session-test/write-on-destruct');
    $this->assertSession()->statusCodeEquals(200);

    // No error was logged while the request was being terminated.
    $errors = \Drupal::database()->select('watchdog', 'w')
      ->fields('w', ['message'])
      ->condition('type', 'php')
      ->execute()
      ->fetchCol();
    $this->assertSame([], $errors);

    // The user session is intact, so the next request is still authenticated.
    $this->drupalGet('user');
    $this->assertSession()->pageTextContains($account->getAccountName());
  }

}
