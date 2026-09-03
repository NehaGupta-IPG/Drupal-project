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
class AggregationDateFieldTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['views.view.test_aggregation'];

  // Add necessary modules including 'datetime'

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
  public function testDateFieldAggregation(): void {
    // Edit the test view 'test_aggregation' to add a date field.
   // Add a new view display or a new view altogether.
    $view = Views::getView('test_aggregation');
    $view->setDisplay();

    // Execute the view.
    $this->executeView($view);

    // Test the format of the aggregated date field.
  }

}
