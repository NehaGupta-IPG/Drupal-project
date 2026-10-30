<?php

declare(strict_types=1);

namespace Drupal\Tests\page_cache\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;

/**
 * Enables the page cache and tests it with various HTTP requests.
 *
 * @group vary
 */
class PageCacheVaryTest extends BrowserTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  protected $dumpHeaders = TRUE;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['test_page_test', 'system_test', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')
      ->set('name', 'Drupal')
      ->set('page.front', '/test-page')
      ->save();
  }

  /**
   * Tests that custom vary header cached.
   */
  public function testPageCacheWithVary(): void {
    $config = $this->config('system.performance');
    $config->set('cache.page.max_age', 300);
    $config->save();

    $this->drupalGet('/test-page-vary-cache', [], ['X-Vary-Test' => 'TestVary1']);
    $this->assertSession()->pageTextContains('TestVary1');
    $this->drupalGet('/test-page-vary-cache', [], ['X-Vary-Test' => 'TestVary1']);
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache', 'HIT');
    $this->drupalGet('/test-page-vary-cache', [], ['X-Vary-Test' => 'TestVary2']);
    $this->assertSession()->pageTextNotContains('TestVary1');
    $this->assertSession()->pageTextContains('TestVary2');
  }

}
