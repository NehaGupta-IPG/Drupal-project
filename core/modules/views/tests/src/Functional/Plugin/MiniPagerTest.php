<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the mini pager plugin.
 *
 * @see \Drupal\views\Plugin\views\pager\Mini
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class MiniPagerTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_mini_pager'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Nodes used by the test.
   *
   * @var array
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, $modules);

    $this->drupalCreateContentType(['type' => 'page']);
    // Create a bunch of test nodes.
    for ($i = 0; $i < 20; $i++) {
      $this->nodes[] = $this->drupalCreateNode();
    }
  }

  /**
   * Tests the rendering of mini pagers.
   */
  public function testMiniPagerRender(): void {
    // On first page, current page and next page link appear, previous page link
    // does not.
    $this->drupalGet('test_mini_pager');
    $this->assertSession()->pageTextContains('›› test');
    $this->assertSession()->pageTextContains('Page 1');
    $this->assertSession()->pageTextNotContains('‹‹ test');
    $this->assertSession()->pageTextContains($this->nodes[0]->label());
    $this->assertSession()->pageTextContains($this->nodes[1]->label());
    $this->assertSession()->pageTextContains($this->nodes[2]->label());

    // On second page, current page and previous/next page links appear.
    $this->drupalGet('test_mini_pager', ['query' => ['page' => 1]]);
    $this->assertSession()->pageTextContains('‹‹ test');
    $this->assertSession()->pageTextContains('Page 2');
    $this->assertSession()->pageTextContains('›› test');
    $this->assertSession()->pageTextContains($this->nodes[3]->label());
    $this->assertSession()->pageTextContains($this->nodes[4]->label());
    $this->assertSession()->pageTextContains($this->nodes[5]->label());

    // On last page, current page and previous page link appear, next page link
    // does not.
    $this->drupalGet('test_mini_pager', ['query' => ['page' => 6]]);
    $this->assertSession()->pageTextNotContains('›› test');
    $this->assertSession()->pageTextContains('Page 7');
    $this->assertSession()->pageTextContains('‹‹ test');
    $this->assertSession()->pageTextContains($this->nodes[18]->label());
    $this->assertSession()->pageTextContains($this->nodes[19]->label());

    // Test @total value in result summary.
    $view = Views::getView('test_mini_pager');
    $view->setDisplay('page_4');
    $this->executeView($view);
    $this->assertTrue($view->get_total_rows, 'The query was set to calculate the total number of rows.');
    $this->assertSame(count($this->nodes), (int) $view->total_rows, 'The total row count is equal to the number of nodes.');

    $this->drupalGet('test_mini_pager_total', ['query' => ['page' => 1]]);
    $this->assertSession()->pageTextContains('of ' . count($this->nodes));
    $this->drupalGet('test_mini_pager_total', ['query' => ['page' => 6]]);
    $this->assertSession()->pageTextContains('of ' . count($this->nodes));

    // Test a mini pager with just one item per page.
    $this->drupalGet('test_mini_pager_one');
    $this->assertSession()->pageTextContains('››');
    $this->assertSession()->pageTextContains('Page 1');
    $this->assertSession()->pageTextContains($this->nodes[0]->label());

    $this->drupalGet('test_mini_pager_one', ['query' => ['page' => 1]]);
    $this->assertSession()->pageTextContains('‹‹');
    $this->assertSession()->pageTextContains('Page 2');
    $this->assertSession()->pageTextContains('››');
    $this->assertSession()->pageTextContains($this->nodes[1]->label());

    $this->drupalGet('test_mini_pager_one', ['query' => ['page' => 19]]);
    $this->assertSession()->pageTextNotContains('››');
    $this->assertSession()->pageTextContains('Page 20');
    $this->assertSession()->pageTextContains('‹‹');
    $this->assertSession()->pageTextContains($this->nodes[19]->label());

    // Test a mini pager with all items on the page. No pager should display.
    $this->drupalGet('test_mini_pager_all');
    $this->assertSession()->pageTextNotContains('‹‹ test');
    $this->assertSession()->pageTextNotContains('Page 1');
    $this->assertSession()->pageTextNotContains('test ››');
    // Verify that all rows appear on the page.
    $this->assertSession()->elementsCount('xpath', "//div[contains(@class, 'views-row')]", count($this->nodes));

    // Remove all items beside 1, so there should be no links shown.
    for ($i = 0; $i < 19; $i++) {
      $this->nodes[$i]->delete();
    }

    $this->drupalGet('test_mini_pager');
    $this->assertSession()->pageTextNotContains('‹‹ test');
    $this->assertSession()->pageTextNotContains('Page 1');
    $this->assertSession()->pageTextNotContains('‹‹ test');
    $this->assertSession()->pageTextContains($this->nodes[19]->label());

    $view = Views::getView('test_mini_pager');
    $this->executeView($view);
    $this->assertNull($view->get_total_rows, 'The query was not forced to calculate the total number of results.');
    $this->assertSame(1, $view->total_rows, 'The pager calculated the total number of rows.');

    // Remove the last node as well and ensure that no "Page 1" is shown.
    $this->nodes[19]->delete();
    $this->drupalGet('test_mini_pager');
    $this->assertSession()->pageTextNotContains('‹‹ test');
    $this->assertSession()->pageTextNotContains('Page 1');
    $this->assertSession()->pageTextNotContains('‹‹ test');
  }

  /**
   * Tests changing the heading level.
   */
  public function testPagerHeadingLevel(): void {
    // Set "Pager Heading" to h3 and check that it is correct.
    $view = Views::getView('test_mini_pager');
    $view->setDisplay();
    $pager = [
      'type' => 'mini',
      'options' => [
        'pagination_heading_level' => 'h3',
        'items_per_page' => 5,
      ],
    ];
    $view->display_handler->setOption('pager', $pager);
    $view->save();

    // Stark and Stable9 are handled below.
    $themes = ['olivero', 'claro', 'starterkit_theme'];
    $this->container->get('theme_installer')->install($themes);

    foreach ($themes as $theme) {
      $this->config('system.theme')->set('default', $theme)->save();
      $this->drupalGet('test_mini_pager');
      $this->assertEquals('h3', $this->assertSession()->elementExists('css', ".pager .visually-hidden")->getTagName());
    }

    // The core views template and Stable9 use a different class structure than
    // other core themes.
    $themes = ['stark', 'stable9'];
    $this->container->get('theme_installer')->install($themes);
    foreach ($themes as $theme) {
      $this->config('system.theme')->set('default', $theme)->save();
      $this->drupalGet('test_mini_pager');
      $this->assertEquals('h3', $this->assertSession()->elementExists('css', "#pagination-heading")->getTagName());
    }
  }

  /**
   * Tests that the mini pager uses '<current>' route for AJAX requests.
   *
   * This ensures that pager links work correctly in AJAX contexts like the
   * media library modal, where the current route should be used instead of
   * a hardcoded route.
   *
   * @see https://www.drupal.org/project/drupal/issues/3567237
   */
  public function testMiniPagerAjaxRoute(): void {
    $view = Views::getView('test_mini_pager');
    $view->setDisplay();
    $view->initPager();

    // Simulate an AJAX request by setting the wrapper format.
    $request = \Drupal::request();
    $request->query->set('_wrapper_format', 'drupal_ajax');

    // Render the pager.
    $pager_render = $view->pager->render([]);

    // Verify that the route_name is '<current>' for AJAX requests.
    $this->assertEquals('<current>', $pager_render['#route_name'], 'Mini pager should use "<current>" route for AJAX requests.');

    // Clean up the request.
    $request->query->remove('_wrapper_format');

    // Test with drupal_modal wrapper format.
    $request->query->set('_wrapper_format', 'drupal_modal');
    $pager_render = $view->pager->render([]);
    $this->assertEquals('<current>', $pager_render['#route_name'], 'Mini pager should use "<current>" route for modal AJAX requests.');

    // Clean up.
    $request->query->remove('_wrapper_format');

    // Test with drupal_dialog wrapper format.
    $request->query->set('_wrapper_format', 'drupal_dialog');
    $pager_render = $view->pager->render([]);
    $this->assertEquals('<current>', $pager_render['#route_name'], 'Mini pager should use "<current>" route for dialog AJAX requests.');

    // Clean up.
    $request->query->remove('_wrapper_format');
  }

  /**
   * Tests that the mini pager uses '<none>' route for non-AJAX requests.
   *
   * This ensures backward compatibility for normal page requests.
   */
  public function testMiniPagerNonAjaxRoute(): void {
    $view = Views::getView('test_mini_pager');
    $view->setDisplay();
    $view->initPager();

    // Ensure no AJAX wrapper format is set.
    $request = \Drupal::request();
    $request->query->remove('_wrapper_format');

    // Render the pager.
    $pager_render = $view->pager->render([]);

    // Verify that the route_name is '<none>' for non-AJAX requests.
    $this->assertEquals('<none>', $pager_render['#route_name'], 'Mini pager should use "<none>" route for non-AJAX requests.');
  }

  /**
   * Tests that the mini pager uses '<current>' route during live preview.
   */
  public function testMiniPagerLivePreviewRoute(): void {
    $view = Views::getView('test_mini_pager');
    $view->setDisplay();
    $view->initPager();

    // Simulate live preview mode.
    $view->live_preview = TRUE;

    // Render the pager.
    $pager_render = $view->pager->render([]);

    // Verify that the route_name is '<current>' during live preview.
    $this->assertEquals('<current>', $pager_render['#route_name'], 'Mini pager should use "<current>" route during live preview.');
  }

}
