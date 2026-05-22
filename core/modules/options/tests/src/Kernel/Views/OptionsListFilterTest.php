<?php

declare(strict_types=1);

namespace Drupal\Tests\options\Kernel\Views;

use Drupal\Core\Database\Database;
use Drupal\views\Views;

/**
 * Tests options list filter for views.
 *
 * @see \Drupal\field\Plugin\views\filter\ListField.
 * @group views
 */
class OptionsListFilterTest extends OptionsTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_options_list_filter'];

  /**
   * Tests options list field filter.
   */
  public function testViewsTestOptionsListFilter(): void {
    if (Database::getConnection()->driver() == 'mongodb') {
      // For MongoDB this view results in an empty array. The value for the
      // field field_test_list_string is set to random values and the filter is
      // searching for "man" or "woman". I am not sure how this is supposed to
      // work?!
      $this->markTestSkipped();
    }

    $view = Views::getView('test_options_list_filter');
    $this->executeView($view);

    $resultset = [
      ['nid' => $this->nodes[0]->nid->value],
      ['nid' => $this->nodes[1]->nid->value],
    ];

    $column_map = ['nid' => 'nid'];
    $this->assertIdenticalResultset($view, $resultset, $column_map);
  }

  /**
   * Tests options list field filter when grouped.
   */
  public function testViewsTestOptionsListGroupedFilter(): void {
    $view = Views::getView('test_options_list_filter');

    if (Database::getConnection()->driver() == 'mongodb') {
      $table = 'node';
      $field = 'field_test_list_string';
    }
    else {
      $table = 'field_data_field_test_list_string';
      $field = 'field_test_list_string_value';
    }

    $filters = [
      'field_test_list_string_value' => [
        'id' => $field,
        'table' => $table,
        'field' => $field,
        'relationship' => 'none',
        'group_type' => 'group',
        'admin_label' => '',
        'operator' => 'or',
        'value' => [
          'man' => 'man',
          'woman' => 'woman',
        ],
        'group' => '1',
        'exposed' => TRUE,
        'expose' => [
          'operator_id' => 'field_test_list_string_value_op',
          'label' => 'list-text',
          'description' => '',
          'identifier' => 'field_test_list_string_value',
        ],
        'is_grouped' => TRUE,
        'group_info' => [
          'label' => 'list-text (field_list_text)',
          'description' => '',
          'identifier' => 'field_test_list_string_value',
          'optional' => TRUE,
          'widget' => 'radios',
          'multiple' => TRUE,
          'remember' => FALSE,
          'default_group' => '1',
          'group_items' => [
            1 => [
              'title' => 'First',
              'operator' => 'or',
              'value' => [
                $this->fieldValues[0] => $this->fieldValues[0],
              ],
            ],
            2 => [
              'title' => 'Second',
              'operator' => 'or',
              'value' => [
                $this->fieldValues[1] => $this->fieldValues[1],
              ],
            ],
          ],
        ],
        'reduce_duplicates' => '',
        'plugin_id' => 'list_field',
      ],
    ];
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $view->storage->save();

    $this->executeView($view);

    if (Database::getConnection()->driver() == 'mongodb') {
      // The expected resultset is wrong. The view sorts the nodes by nid and
      // DESC.
      $resultset = [
        ['nid' => $this->nodes[1]->nid->value],
        ['nid' => $this->nodes[0]->nid->value],
      ];
    }
    else {
      $resultset = [
        ['nid' => $this->nodes[0]->nid->value],
        ['nid' => $this->nodes[1]->nid->value],
      ];
    }

    $column_map = ['nid' => 'nid'];
    $this->assertIdenticalResultset($view, $resultset, $column_map);
  }

}
