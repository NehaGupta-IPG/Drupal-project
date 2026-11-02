<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests Drupal\views\Plugin\views\field\Counter token usage.
 *
 * @group views
 */
class FieldCounterTokenTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  public function viewsData() {
    $data = parent::viewsData();
    $data['views_test_data']['name']['field']['id'] = 'custom';
    return $data;
  }

  /**
   * Tests the counter when used as a token in a link path.
   */
  public function testFieldCounterToken(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Enable the display of the View result counter and the name field.
    // Configure the name field to be output as a link using the counter token.
    $view->displayHandlers->get('default')->overrideOption('fields', [
      'counter' => [
        'id' => 'counter',
        'table' => 'views',
        'field' => 'counter',
        'relationship' => 'none',
        'counter_start' => 0,
        'exclude' => TRUE,
      ],
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
        'alter' => [
          'alter_text' => TRUE,
          'text' => 'Counter {{ counter }}',
          'make_link' => TRUE,
          'path' => '/counter/{{ counter }}',
        ],
      ],
    ]);

    // Execute the view.
    $this->executeView($view);

    $expected_output = '<a href="/counter/2">Counter 2</a>';
    $this->assertSame($expected_output, (string) $view->style_plugin->getField(2, 'name'));
  }

}
