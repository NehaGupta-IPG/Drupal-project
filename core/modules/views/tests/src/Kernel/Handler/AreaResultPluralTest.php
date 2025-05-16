<?php

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests the result plural area handler.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\area\Result
 */
class AreaResultPluralTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = [
    'test_area_result_plural',
  ];

  /**
   * Tests that the singular text is displayed.
   */
  public function testResult() {
    $view = Views::getView('test_area_result_plural');
    $view->setDisplay('default');
    $this->executeView($view);
    $output = $view->render();
    $output = \Drupal::service('renderer')->renderRoot($output);
    $this->setRawContent($output);
    $this->assertText('SINGULAR start: 1 | end: 5 | total: 5 | total formatted: 5 | label: test_area_result_plural | per page: 0 | current page: 1 | current record count: 5 | page count: 1');
  }

  /**
   * Tests that the plural text is displayed.
   */
  public function testResultEmpty() {
    $view = Views::getView('test_area_result_plural');

    // Test that the area is displayed if we have checked the empty checkbox.
    $view->setDisplay('default');

    // Add a filter that will make the result set empty.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
        'operator' => '=',
        'value' => 'non-existing-name',
      ],
    ]);

    $this->executeView($view);
    $output = $view->render();
    $output = \Drupal::service('renderer')->renderRoot($output);
    $this->setRawContent($output);
    $this->assertText('PLURAL start: 0 | end: 0 | total: 0 | total formatted: 0 | label: test_area_result_plural | per page: 0 | current page: 1 | current record count: 0 | page count: 1');
    $this->assertRaw('<header>');

    // Test that the area is not displayed if we have not checked the empty
    // checkbox.
    $view->setDisplay('page_1');

    $this->executeView($view);
    $output = $view->render();
    $output = \Drupal::service('renderer')->renderRoot($output);
    $this->setRawContent($output);
    $this->assertNoText('PLURAL start: 0 | end: 0 | total: 0 | total formatted: 0 | label: test_area_result_plural | per page: 0 | current page: 1 | current record count: 0 | page count: 1');
    // Make sure the empty header region isn't rendered.
    $this->assertNoRaw('<header>');
  }

}
