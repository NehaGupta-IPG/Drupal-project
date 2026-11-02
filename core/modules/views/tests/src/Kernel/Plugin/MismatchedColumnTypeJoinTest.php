<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests joins between columns that store their values as a different type.
 *
 * Entity IDs are integers for most entity types and strings for others, and
 * tables that track entities of any type store all of them as strings. Joining
 * such a column with an integer column has to work on every database engine.
 *
 * @see \Drupal\views\Plugin\views\join\JoinPluginBase::buildJoin()
 * @see \Drupal\views\Plugin\views\query\CastSqlInterface::getComparableOperands()
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class MismatchedColumnTypeJoinTest extends RelationshipJoinTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    // Track two of the {views_test_data} rows by their ID as a string. The
    // third row tracks a string ID, which an entity type is allowed to use and
    // which can never be equal to an integer ID.
    $query = Database::getConnection()->insert('views_test_string_id')
      ->fields(['id', 'label']);
    foreach ([['1', 'first'], ['2', 'second'], ['a_string_id', 'not a number']] as [$id, $label]) {
      $query->values(['id' => $id, 'label' => $label]);
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  protected function schemaDefinition(): array {
    $schema = parent::schemaDefinition();

    // A table that stores the ID of the row it tracks as a string, the way
    // {file_usage} does.
    $schema['views_test_string_id'] = [
      'description' => 'A table that stores the IDs it tracks as strings.',
      'fields' => [
        'id' => [
          'description' => 'The {views_test_data}.id this row tracks.',
          'type' => 'varchar_ascii',
          'length' => 64,
          'not null' => TRUE,
        ],
        'label' => [
          'description' => 'The label of this row.',
          'type' => 'varchar',
          'length' => 32,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['id'],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewsData(): array {
    $data = parent::viewsData();

    $data['views_test_string_id']['table']['group'] = new TranslatableMarkup('Views test string ID');
    $data['views_test_string_id']['table']['join']['views_test_data'] = [
      'left_field' => 'id',
      'field' => 'id',
    ];
    $data['views_test_string_id']['label'] = [
      'title' => new TranslatableMarkup('Label'),
      'help' => new TranslatableMarkup('The label of the tracked row.'),
      'field' => [
        'id' => 'standard',
      ],
    ];

    return $data;
  }

  /**
   * Tests a join from an integer column to a column that stores strings.
   */
  public function testJoinToStringColumn(): void {
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();

    $configuration = [
      'left_table' => 'views_test_data',
      'left_field' => 'id',
      'table' => 'views_test_string_id',
      'field' => 'id',
      'type' => 'INNER',
    ];
    $join = $this->container->get('plugin.manager.views.join')
      ->createInstance('standard', $configuration);

    $query = Database::getConnection()->select('views_test_data');
    $query->addField('views_test_data', 'name');
    $query->addField('views_test_string_id', 'label');
    $join->buildJoin($query, ['alias' => 'views_test_string_id'], $view->query);

    $this->assertSame(['John' => 'first', 'George' => 'second'], $query->execute()->fetchAllKeyed());
  }

  /**
   * Tests a join from a column that stores strings to an integer column.
   */
  public function testJoinFromStringColumn(): void {
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();
    // Make the table that stores its IDs as strings the left table of the join.
    $view->query->ensureTable('views_test_string_id');

    $configuration = [
      'left_table' => 'views_test_string_id',
      'left_field' => 'id',
      'table' => 'views_test_data',
      'field' => 'id',
      'type' => 'INNER',
    ];
    $join = $this->container->get('plugin.manager.views.join')
      ->createInstance('standard', $configuration);

    $query = Database::getConnection()->select('views_test_string_id');
    $query->addField('views_test_string_id', 'label');
    $query->addField('views_test_data', 'name');
    $join->buildJoin($query, ['alias' => 'views_test_data'], $view->query);

    $this->assertSame(['first' => 'John', 'second' => 'George'], $query->execute()->fetchAllKeyed());
  }

  /**
   * Tests a view that joins to a column storing its IDs as strings.
   */
  public function testViewWithMismatchedColumnTypes(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->displayHandlers->get('default')->overrideOption('fields', [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
      ],
      'label' => [
        'id' => 'label',
        'table' => 'views_test_string_id',
        'field' => 'label',
      ],
    ]);
    $this->executeView($view);

    // Every row of the base table is listed, and the two rows that are tracked
    // by the table storing its IDs as strings have a label.
    $expected = [
      ['name' => 'John', 'label' => 'first'],
      ['name' => 'George', 'label' => 'second'],
      ['name' => 'Ringo', 'label' => ''],
      ['name' => 'Paul', 'label' => ''],
      ['name' => 'Meredith', 'label' => ''],
    ];
    $column_map = [
      'views_test_data_name' => 'name',
      'views_test_string_id_label' => 'label',
    ];
    $this->assertIdenticalResultset($view, $expected, $column_map);
  }

}
