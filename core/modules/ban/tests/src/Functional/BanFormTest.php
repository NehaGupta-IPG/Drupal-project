<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests various behaviors related to UI and admin Form.
 *
 * @group ban
 */
class BanFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ban',
  ];

  /**
   * A user with authenticated permissions.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $user;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')->set('page.front', '/test-page')->save();
    $this->user = $this->drupalCreateUser([]);
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Tests unblocking multiple IP addresses via the UI.
   */
  public function testUnblocking(): void {
    // Create user.
    $admin_user = $this->drupalCreateUser(['ban IP addresses']);
    $this->drupalLogin($admin_user);
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // Create five IP addresses to test the unblocking on.
    $edit = [];
    $ipAddresses = ['1.2.3.4', '11.22.33.44', '123.123.123.123', '111.111.111.111', '222.222.222.222'];
    $this->drupalGet('admin/config/people/ban');
    foreach ($ipAddresses as $ipAddress) {
      $edit['ip'] = $ipAddress;
      $this->submitForm($edit, 'edit-submit');
    }
    // Check that all IP addresses are in the list.
    foreach ($ipAddresses as $ipAddress) {
      $session->pageTextContains($ipAddress);
    }
    // Remove the first and third IP address via the checkboxes and check the list again.
    $page->checkField('edit-ban-ip-banning-table-0');
    $page->checkField('edit-ban-ip-banning-table-2');
    $page->pressButton('edit-delete');
    // Redirection to the unblock confirmation form. Check that only the selected IP addresses are being deleted.
    $session->statusCodeEquals(200);
    $session->pageTextContains('1.2.3.4');
    $session->pageTextNotContains('11.22.33.44');
    $session->pageTextNotContains('123.123.123.123');
    $session->pageTextContains('111.111.111.111');
    $session->pageTextNotContains('222.222.222.222');
    $page->pressButton('edit-submit');
    // The IP addresses are deleted now and we are on the list page again. Check that the deleted IP addresses are gone.
    $session->statusCodeEquals(200);
    $session->pageTextNotContains('1.2.3.4');
    $session->pageTextContains('11.22.33.44');
    $session->pageTextContains('123.123.123.123');
    $session->pageTextNotContains('111.111.111.111');
    $session->pageTextContains('222.222.222.222');
  }

}
