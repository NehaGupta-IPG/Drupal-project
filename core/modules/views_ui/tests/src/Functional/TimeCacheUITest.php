<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Functional;

use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UI of the time-based cache plugin.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class TimeCacheUITest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_cache'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests configuring custom and preset lifespans for time-based caching.
   */
  public function testTimeCacheUI(): void {
    // The page displays of the test view have no path configured, which
    // prevents saving the view in the UI.
    $view = View::load('test_cache');
    foreach (['page_1', 'page_2'] as $display_id) {
      $display = &$view->getDisplay($display_id);
      $display['display_options']['path'] = 'test-cache-' . $display_id;
    }
    $view->save();

    $cache_options_path = 'admin/structure/views/nojs/display/test_cache/page_2/cache_options';

    // The configured lifespans of 3600 seconds match a preset.
    $this->drupalGet($cache_options_path);
    $this->assertTrue($this->assertSession()->optionExists('cache_options[results_lifespan]', '3600')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('cache_options[output_lifespan]', '3600')->isSelected());

    // Custom lifespans must be numeric.
    $this->submitForm([
      'cache_options[results_lifespan]' => 'custom',
      'cache_options[results_lifespan_custom]' => 'invalid',
    ], 'Apply');
    $this->assertSession()->pageTextContains('Custom time values must be numeric.');

    // Save custom lifespans.
    $this->submitForm([
      'cache_options[results_lifespan]' => 'custom',
      'cache_options[results_lifespan_custom]' => '604800',
      'cache_options[output_lifespan]' => 'custom',
      'cache_options[output_lifespan_custom]' => '86400',
    ], 'Apply');
    $this->submitForm([], 'Save');

    // Custom lifespans are stored directly in the lifespan options.
    $options = $this->config('views.view.test_cache')->get('display.page_2.display_options.cache.options');
    $this->assertSame(604800, $options['results_lifespan']);
    $this->assertSame(86400, $options['output_lifespan']);
    $this->assertSame(['results_lifespan', 'output_lifespan'], array_keys($options));

    // The form preselects "Custom" and shows the custom lifespans.
    $this->drupalGet($cache_options_path);
    $this->assertTrue($this->assertSession()->optionExists('cache_options[results_lifespan]', 'custom')->isSelected());
    $this->assertSession()->fieldValueEquals('cache_options[results_lifespan_custom]', '604800');
    $this->assertTrue($this->assertSession()->optionExists('cache_options[output_lifespan]', 'custom')->isSelected());
    $this->assertSession()->fieldValueEquals('cache_options[output_lifespan_custom]', '86400');
  }

}
