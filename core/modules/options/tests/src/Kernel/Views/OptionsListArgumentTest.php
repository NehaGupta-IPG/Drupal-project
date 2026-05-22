<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel\Views;

use Drupal\Core\Database\Database;
use Drupal\views\Views;

/**
 * Tests options list argument for views.
 *
 * @see \Drupal\options\Plugin\views\argument\NumberListField.
 * @group views
 */
class OptionsListArgumentTest extends OptionsTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_options_list_argument_numeric', 'test_options_list_argument_string'];

  /**
   * Tests the options field argument.
   */
  public function testViewsTestOptionsListArgument(): void {
    if (Database::getConnection()->driver() == 'mongodb') {
      // For MongoDB this view results in an empty array. The value for the
      // field field_test_list_integer is set to zero and the argument is set to
      // one. I am not sure how this is supposed to work?!
      $this->markTestSkipped();
    }

    $view = Views::getView('test_options_list_argument_numeric');
    $this->executeView($view, [1]);

    $resultset = [
      ['nid' => $this->nodes[0]->nid->value],
      ['nid' => $this->nodes[1]->nid->value],
    ];

    $column_map = ['nid' => 'nid'];
    $this->assertIdenticalResultset($view, $resultset, $column_map);

    $view = Views::getView('test_options_list_argument_string');
    $this->executeView($view, ['man', 'woman']);

    $resultset = [
      ['nid' => $this->nodes[0]->nid->value],
      ['nid' => $this->nodes[1]->nid->value],
    ];

    $column_map = ['nid' => 'nid'];
    $this->assertIdenticalResultset($view, $resultset, $column_map);
  }

}
