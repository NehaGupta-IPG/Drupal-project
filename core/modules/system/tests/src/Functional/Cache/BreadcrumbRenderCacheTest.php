<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Cache;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\system\Functional\Menu\AssertBreadcrumbTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Breadcrumb Render Cache.
 */
#[Group('Cache')]
#[Group('Breadcrumb')]
#[RunTestsInSeparateProcesses]
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
  public function testHomepage(): void {
    // Test homepage.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);

    // Minimal homepage title.
    $this->assertSession()->pageTextContains('Log in');
  }

  /**
   * Tests breadcrumb render cache.
   */
  public function testBreadCrumbRenderCache(): void {
    // Visit a child page of the title-test route, so the breadcrumb has a
    // real parent pages.
    $url1 = Url::fromRoute('breadcrumb_render_cache_test.title_test_child', ['api_id' => '564564564'])->toString();
    $this->drupalGet($url1);
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('564564564');

    $url2 = Url::fromRoute('breadcrumb_render_cache_test.title_test_child', ['api_id' => '234264'])->toString();
    $this->drupalGet($url2);
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('234264');
    $assert->pageTextNotContains('564564564');
    $home_path = Url::fromRoute('<front>')->toString();
    $ancestor_url = Url::fromRoute('breadcrumb_render_cache_test.title_test', ['api_id' => '234264'])->toString();

    $this->assertBreadcrumb($url2, [
      $home_path => 'Home',
      $ancestor_url => 'API Response for : 234264',
    ]);

  }

}
