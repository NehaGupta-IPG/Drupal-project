<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Cache;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;

/**
 * Tests Breadcrumb Render Cache.
 *
 * @group Cache
 * @group Breadcrumb
 */
class BreadcrumbRenderCacheTest extends BrowserTestBase {

  use AssertBreadcrumbTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'node',
    'block',
    'breadcrumb_render_cache_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('system_breadcrumb_block');

  }

  /**
   * Tests Homepage.
   */
  public function testHomepage() {
    // Test homepage.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Minimal homepage title.
    $this->assertSession()->pageTextContains('Log in');
  }

  /**
   * Tests BreadCrumb Render Cache.
   */
  public function testBreadCrumbRenderCache() {
    $url = Url::fromRoute('breadcrumb_render_cache_test.title_test', [])->toString();
    $url1 = Url::fromRoute('breadcrumb_render_cache_test.title_test', ['api_id' => '564564564'])->toString();
    $this->drupalGet($url1);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('564564564');

    $url2 = Url::fromRoute('breadcrumb_render_cache_test.title_test', ['api_id' => '234264'])->toString();
    $this->drupalGet($url2);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('234264');
    $this->assertSession()->pageTextNotContains('564564564');
    $home_path = Url::fromRoute('<front>')->toString();

    $this->assertBreadcrumb($url2, [
      $home_path => 'Home',
      $url2 => 'API Response for : 234264',
    ]);

  }

}
