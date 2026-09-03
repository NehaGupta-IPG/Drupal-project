<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel;

use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A basic aggregation test for Views.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class AggregationTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['views.view.test_aggregation'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_test_aggregation', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installConfig('views_test_aggregation');

    $this->installEntitySchema('user');
  }

  /**
   * Tests an aggregated date field.
   */
  public function testDateFieldAggregationResultSet(): void {
    // todo: Edit the test view 'test_aggregation' to add a date field e.g.
    // perhaps a new view display or a new view altogether.
    $view = Views::getView('test_aggregation');
    $view->setDisplay();

    // Execute the view.
    $this->executeView($view);

    // todo: create assertions to test that the aggregated date field is output
    // correctly.
    // Verify that the aggregated date is formatted correctly.
  }

}
