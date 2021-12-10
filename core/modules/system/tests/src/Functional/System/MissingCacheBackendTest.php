<?php

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;

/**
 * Test system_requirements related to missing cache backend.
 *
 * @group system
 */
class MissingCacheBackendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['cache_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createUser(['administer site configuration']));
  }

  /**
   * Tests warnings correctly appear on status page.
   */
  public function testStatusPage() {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/reports/status');

    // A couple services are configured with non-existent backends.
    $assert_session->pageTextContains('The configured cache backend cache.backend.missing is not available for the bin cache_test_missing');
    $assert_session->pageTextContains('The configured cache backend cache.backend.lost is not available for the bin cache_test_lost');
    // A couple services have valid configuration and should have no warning.
    $assert_session->pageTextNotContains('cache_test_memory');
    $assert_session->pageTextNotContains('cache_test_no_default');
  }

}
