<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests the "Some" pager.
 *
 * @group views
 */
class SomePagerTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_tokens'];

  /**
   * Tests that the "more" link works correctly with the "Some" pager.
   *
   * @see \Drupal\views\Plugin\views\pager\Some::hasMoreRecords()
   */
  public function testSomeHasMoreRecords(): void {
    $view = Views::getView('test_tokens');
    $view->setDisplay('page_4');
    $this->executeView($view);

    $total_rows_in_table = ViewTestData::dataSet();
    $this->assertSame(3, $view->total_rows);
    $this->assertGreaterThan(3, count($total_rows_in_table));

    $this->assertTrue($view->getPager()->hasMoreRecords());
  }

}
