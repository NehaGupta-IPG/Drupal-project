<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Functional tests for Allowed IP administration UI.
 *
 * @group ban
 */
final class AllowedIpUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ban',
    'user',
    'system',
  ];

  /**
   * Use a minimal core theme for functional tests.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with needed permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests add/edit/delete Allowed IP via UI.
   */
  public function testAllowedIpCrud(): void {
    $this->drupalGet('/admin/config/people/ban-allowed');
    $this->assertSession()->statusCodeEquals(200);

    $this->clickLink('Add Allowed IP');
    $this->assertSession()->addressEquals('/admin/config/people/ban-allowed/add');

    $ip = '203.0.113.99';
    $this->submitForm(['allowedIp' => $ip], 'Save');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($ip);

    $this->clickLink('Edit');
    $this->assertSession()->statusCodeEquals(200);

    $new_ip = '203.0.113.100';
    $this->submitForm(['allowedIp' => $new_ip], 'Save');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($ip);
    $this->assertSession()->pageTextContains($new_ip);

    $this->clickLink('Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($new_ip);
  }

  /**
   * Tests validation: invalid IP and duplicate.
   */
  public function testValidation(): void {
    $this->drupalGet('/admin/config/people/ban-allowed/add');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm(['allowedIp' => 'not-an-ip'], 'Save');
    $this->assertSession()->pageTextContains('Not a valid IP.');

    $ip = '198.51.100.200';
    $this->submitForm(['allowedIp' => $ip], 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($ip);

    $this->drupalGet('/admin/config/people/ban-allowed/add');
    $this->submitForm(['allowedIp' => $ip], 'Save');
    $this->assertSession()->pageTextContains('already');
  }

}
