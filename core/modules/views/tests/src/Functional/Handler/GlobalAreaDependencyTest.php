<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Handler;

use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\node\Entity\Node;
use Drupal\views\Views;

/**
 * Tests the Global: View area option..
 *
 * @group views
 */
class GlobalAreaDependencyTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_global_area_view_child', 'test_global_area_view_parent'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['views_test_config']): void {
    parent::setUp($import_test_views, $modules);

    $this->enableViewsTestModule();
    // Creating content type for testing.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
  }

  /**
   * Tests the Global: View area option.
   */
  public function testGlobalViewAreaDependsOnParentResults(): void {

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');
    $view = Views::getView('test_global_area_view_parent');
    // The default view display.
    // The "Display even if view has no result" option is turned off.
    $view->setDisplay();
    // Node created(published) for child view.
    // The parent view has no results now.
    Node::create(['type' => 'page', 'title' => 'Node from Child View', 'status' => 1])->save();

    $this->executeView($view);
    $output = $view->render();
    $output = (string) $renderer->renderRoot($output);

    // Checking if view result from child view is displaying.
    $this->assertStringNotContainsString('Node from Child View', $output, 'The child view results are rendering when the "Display even if view has no result" option is turned off.');
    $view->destroy();

    // The "Display even if view has no result" option is turned on.
    $view->setDisplay('display_if_no_result');
    $this->executeView($view);
    $output = $view->render();
    $output = (string) $renderer->renderRoot($output);
    $this->assertStringContainsString('Node from Child View', $output, 'The child view results are not rendering when the "Display even if view has no result" option is turned on.');
    $view->destroy();

    // Creating node(unpublished) for Parent view.
    Node::create(['type' => 'page', 'title' => 'Node from Parent View', 'status' => 0])->save();

    // Parent view has results.
    // The "Display even if view has no result" option is turned on.
    $this->executeView($view);
    $output = $view->render();
    $output = (string) $renderer->renderRoot($output);
    // Checking both contents are displaying from both views.
    $this->assertStringContainsString('Node from Parent View', $output, 'The parent view results are not rendering.');
    $this->assertStringContainsString('Node from Child View', $output, 'The child view results are not rendering.');

    // Parent view has results.
    // The "Display even if view has no result" option is turned off.
    $view->destroy();
    $view->setDisplay();
    $this->executeView($view);
    $output = $view->render();
    $output = (string) $renderer->renderRoot($output);
    $this->assertStringContainsString('Node from Parent View', $output, 'The parent view results are not rendering.');
    $this->assertStringContainsString('Node from Child View', $output, 'The child view results are not rendering.');

  }

}
