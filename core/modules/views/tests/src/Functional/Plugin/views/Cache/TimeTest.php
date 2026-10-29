<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin\views\Cache;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests pluggable access for views.
 *
 * @group views
 */
class TimeTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_cache'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();

    ViewTestData::createTestViews(static::class, ['views_test_data']);

    $admin_user = $this->drupalCreateUser([
      'administer views',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests view time cache custom setting.
   */
  public function testCustomTimeCache() {
    $this->drupalGet('admin/structure/views/view/test_cache/edit/page_3');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->linkExists('10 sec/15 sec');

    $this->clickLink('10 sec/15 sec');
    $this->assertTrue($this->assertSession()->optionExists('cache_options[results_lifespan]', 'Custom')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('cache_options[output_lifespan]', 'Custom')->isSelected());
  }

}
