<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Handler;

use Drupal\Core\Database\SchemaDefinition\Column;
use Drupal\Core\Database\SchemaDefinition\ColumnType;
use Drupal\Core\Database\SchemaDefinition\Schema;
use Drupal\Core\Database\SchemaDefinition\StringValue;
use Drupal\Core\Database\SchemaDefinition\Table;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests core's BooleanOperatorString views filter handler.
 *
 * @see \Drupal\views\Plugin\views\filter\BooleanOperatorString
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class FilterBooleanOperatorStringTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Map column names.
   *
   * @var array
   */
  protected $columnMap = [
    'views_test_data_id' => 'id',
  ];

  /**
   * {@inheritdoc}
   */
  protected function schemaDefinition(): Schema {
    $parentSchema = parent::schemaDefinition();
    $table = $parentSchema->getTableDefinition('views_test_data');
    $columns = array_filter($table->columns, fn (Column $column): bool => $column->name !== 'status');
    $columns[] = new Column(
      name: 'status',
      description: 'The status of this record',
      type: ColumnType::Varchar,
      length: 255,
      notNull: TRUE,
      default: new StringValue(''),
    );

    return new Schema(
      name: $parentSchema->name,
      tables: [
        new Table(
          name: $table->name,
          description: $table->description,
          columns: $columns,
          primaryKey: $table->primaryKey,
          uniqueKeys: $table->uniqueKeys,
          indexes: $table->indexes,
        ),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function viewsData() {
    $views_data = parent::viewsData();

    $views_data['views_test_data']['status']['filter']['id'] = 'boolean_string';

    return $views_data;
  }

  /**
   * {@inheritdoc}
   */
  protected function dataSet() {
    $data = parent::dataSet();

    foreach ($data as &$row) {
      if ($row['status']) {
        $row['status'] = 'Enabled';
      }
      else {
        $row['status'] = '';
      }
    }

    return $data;
  }

  /**
   * Tests the BooleanOperatorString filter.
   */
  public function testFilterBooleanOperatorString(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a the status boolean filter.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'value' => 0,
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 2],
      ['id' => 4],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    // Add the status boolean filter.
    $view->displayHandlers->get('default')->overrideOption('filters', [
      'status' => [
        'id' => 'status',
        'field' => 'status',
        'table' => 'views_test_data',
        'value' => 1,
      ],
    ]);
    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Tests the Boolean filter with grouped exposed form enabled.
   */
  public function testFilterGroupedExposed(): void {
    $filters = $this->getGroupedExposedFilters();
    $view = Views::getView('test_view');

    $view->setExposedInput(['status' => 1]);
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $this->executeView($view);

    $expected_result = [
      ['id' => 1],
      ['id' => 3],
      ['id' => 5],
    ];

    $this->assertCount(3, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
    $view->destroy();

    $view->setExposedInput(['status' => 2]);
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('filters', $filters);

    $this->executeView($view);

    $expected_result = [
      ['id' => 2],
      ['id' => 4],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Provides grouped exposed filter configuration.
   *
   * @return array
   *   Returns the filter configuration for exposed filters.
   */
  protected function getGroupedExposedFilters(): array {
    return [
      'status' => [
        'id' => 'status',
        'table' => 'views_test_data',
        'field' => 'status',
        'relationship' => 'none',
        'exposed' => TRUE,
        'expose' => [
          'operator' => 'status_op',
          'label' => 'status',
          'identifier' => 'status',
        ],
        'is_grouped' => TRUE,
        'group_info' => [
          'label' => 'status',
          'identifier' => 'status',
          'default_group' => 'All',
          'group_items' => [
            1 => [
              'title' => 'Active',
              'operator' => '=',
              'value' => '1',
            ],
            2 => [
              'title' => 'Blocked',
              'operator' => '=',
              'value' => '0',
            ],
          ],
        ],
      ],
    ];
  }

}
