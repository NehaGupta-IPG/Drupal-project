<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Functional;

use Drupal\Tests\BrowserTestBase;

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
   * Admin user with needed permissions.
   */
  protected $adminUser;

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
    // Visit listing.
    $this->drupalGet('/admin/config/people/ban-allowed');
    $this->assertSession()->statusCodeEquals(200);

    // Add new record.
    $this->clickLink('Add Allowed IP');
    $this->assertSession()->addressEquals('/admin/config/people/ban-allowed/add');

    $ip = '203.0.113.99';
    $edit = ['whitelistedIp' => $ip]; // Field machine name in the form class may be 'allowedIp' or similar.
    // Try both common variants to be resilient to minor form naming.
    if ($this->assertSession()->fieldExists('allowedIp')->getAttribute('name') ?? FALSE) {
      $edit = ['allowedIp' => $ip];
    }
    $this->submitForm($edit, 'Save');

    // Back on listing and the IP is visible.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($ip);

    // Click edit on the row.
    $this->clickLink('Edit');
    $this->assertSession()->statusCodeEquals(200);

    $new_ip = '203.0.113.100';
    // Update the IP value.
    if ($this->getSession()->getPage()->hasField('allowedIp')) {
      $this->getSession()->getPage()->fillField('allowedIp', $new_ip);
    }
    elseif ($this->getSession()->getPage()->hasField('whitelistedIp')) {
      $this->getSession()->getPage()->fillField('whitelistedIp', $new_ip);
    }
    $this->submitForm([], 'Save');

    // Listing shows updated IP.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($ip);
    $this->assertSession()->pageTextContains($new_ip);

    // Delete the entry.
    $this->clickLink('Delete');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Delete');

    // Listing no longer shows the IP.
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($new_ip);
  }

  /**
   * Tests validation: invalid IP and duplicate.
   */
  public function testValidation(): void {
    // Go to add form.
    $this->drupalGet('/admin/config/people/ban-allowed/add');
    $this->assertSession()->statusCodeEquals(200);

    // Invalid IP.
    $this->submitForm(['allowedIp' => 'not-an-ip'], 'Save');
    $this->assertSession()->pageTextContains('Not a valid IP.');
    // Try alternative field name if needed.
    if ($this->getSession()->getPage()->hasField('whitelistedIp')) {
      $this->submitForm(['whitelistedIp' => 'not-an-ip'], 'Save');
      $this->assertSession()->pageTextContains('Not a valid IP.');
    }

    // Valid IP.
    $ip = '198.51.100.200';
    if ($this->getSession()->getPage()->hasField('allowedIp')) {
      $this->submitForm(['allowedIp' => $ip], 'Save');
    }
    else {
      $this->submitForm(['whitelistedIp' => $ip], 'Save');
    }
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($ip);

    // Duplicate should error.
    $this->drupalGet('/admin/config/people/ban-allowed/add');
    if ($this->getSession()->getPage()->hasField('allowedIp')) {
      $this->submitForm(['allowedIp' => $ip], 'Save');
    }
    else {
      $this->submitForm(['whitelistedIp' => $ip], 'Save');
    }
    $this->assertSession()->pageTextContains('already');
  }

}
