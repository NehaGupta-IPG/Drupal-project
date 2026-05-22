<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Core\Database\Database;
use Drupal\views_test_data\Plugin\views\join\JoinTest as JoinTestPlugin;
use Drupal\views\Plugin\views\join\JoinPluginBase;
use Drupal\views\Views;

/**
 * Tests the join plugin.
 *
 * @group views
 * @see \Drupal\views_test_data\Plugin\views\join\JoinTest
 * @see \Drupal\views\Plugin\views\join\JoinPluginBase
 */
class JoinTest extends RelationshipJoinTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * A plugin manager which handlers the instances of joins.
   *
   * @var \Drupal\views\Plugin\ViewsPluginManager
   */
  protected $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    // Add a join plugin manager which can be used in all of the tests.
    $this->manager = $this->container->get('plugin.manager.views.join');
  }

  /**
   * Tests an example join plugin.
   */
  public function testExamplePlugin(): void {

    // Setup a simple join and test the result sql.
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();

    $configuration = [
      'left_table' => 'views_test_data',
      'left_field' => 'uid',
      'table' => 'users_field_data',
      'field' => 'uid',
    ];
    $join = $this->manager->createInstance('join_test', $configuration);
    $this->assertInstanceOf(JoinTestPlugin::class, $join);

    $rand_int = rand(0, 1000);
    $join->setJoinValue($rand_int);

    $connection = Database::getConnection();
    $query = $connection->select('views_test_data');
    $table = ['alias' => 'users_field_data'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);
      $mongodb_joins = $query->getTables();
      $join_info = $mongodb_joins['users_field_data'];

      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $expected_condition = [
        '$and' => [
          [
            'users_field_data.uid' => [
              '$eq' => '$views_test_data.uid',
            ],
          ],
          [
            'views_test_data.uid' => [
              '$eq' => $rand_int,
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame([], array_values($condition->arguments()));
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users_field_data'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertStringContainsString('"views_test_data"."uid" = :db_condition_placeholder_1', $condition->__toString(), 'Make sure that the custom join plugin can extend the join base and alter the result.');

    }
  }

  /**
   * Tests the join plugin base.
   */
  public function testBasePlugin(): void {
    // Setup a simple join and test the result sql.
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();

    // First define a simple join without an extra condition.
    // Set the various options on the join object.
    $configuration = [
      'left_table' => 'views_test_data',
      'left_field' => 'uid',
      'table' => 'users_field_data',
      'field' => 'uid',
      'adjusted' => TRUE,
    ];
    $join = $this->manager->createInstance('standard', $configuration);
    $this->assertInstanceOf(JoinPluginBase::class, $join);
    $this->assertNull($join->extra, 'The field extra was not overridden.');
    $this->assertTrue($join->adjusted, 'The field adjusted was set correctly.');

    // Build the actual join values and read them back from the dbtng query
    // object.
    $connection = Database::getConnection();
    $query = $connection->select('views_test_data');
    $table = ['alias' => 'users_field_data'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users_field_data'];

      $this->assertSame('LEFT', $join_info['join type'], 'Make sure the default join type is LEFT');
      $this->assertSame($configuration['table'], $join_info['table']);
      $this->assertSame('users_field_data', $join_info['alias']);

      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $expected_condition = [
        'views_test_data.uid' => [
          '$eq' => '$users_field_data.uid',
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame([], array_values($condition->arguments()));
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users_field_data'];
      $this->assertEquals('LEFT', $join_info['join type'], 'Make sure the default join type is LEFT');
      $this->assertEquals($configuration['table'], $join_info['table']);
      $this->assertEquals('users_field_data', $join_info['alias']);
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertEquals('"views_test_data"."uid" = "users_field_data"."uid"', $condition->__toString());
    }

    // Set a different alias and make sure table info is as expected.
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users1'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);
    }
    else {
      $join->buildJoin($query, $table, $view->query);
    }

    $tables = $query->getTables();
    $join_info = $tables['users1'];
    $this->assertEquals('users1', $join_info['alias']);

    // Set a different join type (INNER) and make sure it is used.
    $configuration['type'] = 'INNER';
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users2'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);
    }
    else {
      $join->buildJoin($query, $table, $view->query);
    }
    $tables = $query->getTables();
    $join_info = $tables['users2'];
    $this->assertEquals('INNER', $join_info['join type']);

    // Setup addition conditions and make sure it is used.
    $random_name_1 = $this->randomMachineName();
    $random_name_2 = $this->randomMachineName();
    $configuration['extra'] = [
      [
        'field' => 'name',
        'value' => $random_name_1,
      ],
      [
        'field' => 'name',
        'value' => $random_name_2,
        'operator' => '<>',
      ],
    ];
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users3'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users3'];
      $condition = $join_info['condition'];
      $condition->setMongodbJoinCondition();
      $condition->compile($connection, $query);
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.uid',
                '$users3.uid',
              ],
            ],
          ],
          [
            '$and' => [
              [
                '$expr' => [
                  '$eq' => [
                    '$users3.name',
                    $random_name_1,
                  ],
                ],
              ],
              [
                '$expr' => [
                  '$ne' => [
                    '$users3.name',
                    $random_name_2,
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame([], array_values($condition->arguments()));
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users3'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertStringContainsString('"views_test_data"."uid" = "users3"."uid"', $condition->__toString(), 'Make sure the join condition appears in the query.');
      $this->assertStringContainsString('"users3"."name" = :db_condition_placeholder_2', $condition->__toString(), 'Make sure the first extra join condition appears in the query and uses the first placeholder.');
      $this->assertStringContainsString('"users3"."name" <> :db_condition_placeholder_3', $condition->__toString(), 'Make sure the second extra join condition appears in the query and uses the second placeholder.');
      $this->assertEquals([
        $random_name_1,
        $random_name_2,
      ], array_values($condition->arguments()), 'Make sure the arguments are in the right order');
    }

    // Test that 'IN' conditions are properly built.
    $random_name_1 = $this->randomMachineName();
    $random_name_2 = $this->randomMachineName();
    $random_name_3 = $this->randomMachineName();
    $random_name_4 = $this->randomMachineName();
    $configuration['extra'] = [
      [
        'field' => 'name',
        'value' => $random_name_1,
      ],
      [
        'field' => 'name',
        'value' => [$random_name_2, $random_name_3, $random_name_4],
      ],
    ];
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users4'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users4'];
      $condition = $join_info['condition'];
      $condition->setMongodbJoinCondition();
      $condition->compile($connection, $query);
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.uid',
                '$users4.uid',
              ],
            ],
          ],
          [
            '$and' => [
              [
                '$expr' => [
                  '$eq' => [
                    '$users4.name',
                    $random_name_1,
                  ],
                ],
              ],
              [
                '$expr' => [
                  '$in' => [
                    '$users4.name',
                    [
                      $random_name_2,
                      $random_name_3,
                      $random_name_4,
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame([], array_values($condition->arguments()));
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users4'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertStringContainsString('"views_test_data"."uid" = "users4"."uid"', $condition->__toString(), 'Make sure the join condition appears in the query.');
      $this->assertStringContainsString('"users4"."name" = :db_condition_placeholder_6', $condition->__toString(), 'Make sure the first extra join condition appears in the query.');
      $this->assertStringContainsString('"users4"."name" IN (:db_condition_placeholder_7, :db_condition_placeholder_8, :db_condition_placeholder_9)', $condition->__toString(), 'The IN condition for the join is properly formed.');
      $this->assertEquals([$random_name_1, $random_name_2, $random_name_3, $random_name_4], array_values($condition->arguments()), 'Make sure the IN arguments are still part of an array.');
    }

    // Test that all the conditions are properly built.
    $configuration['extra'] = [
      [
        'field' => 'langcode',
        'value' => 'en',
      ],
      [
        'left_field' => 'status',
        'value' => 0,
        'numeric' => TRUE,
      ],
      [
        'field' => 'name',
        'left_field' => 'name',
      ],
    ];
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users5'];
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users5'];
      $condition = $join_info['condition'];
      $condition->setMongodbJoinCondition();
      $condition->compile($connection, $query);
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.uid',
                '$users5.uid',
              ],
            ],
          ],
          [
            '$and' => [
              [
                '$expr' => [
                  '$eq' => [
                    '$users5.langcode',
                    'en',
                  ],
                ],
              ],
              [
                '$expr' => [
                  '$eq' => [
                    '$views_test_data.status',
                    0,
                  ],
                ],
              ],
              [
                '$expr' => [
                  '$eq' => [
                    '$users5.name',
                    '$views_test_data.name',
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame([], array_values($condition->arguments()));
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users5'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertStringContainsString('"views_test_data"."uid" = "users5"."uid"', $condition->__toString(), 'Make sure the join condition appears in the query.');
      $this->assertStringContainsString('"users5"."langcode" = :db_condition_placeholder_13', $condition->__toString(), 'Make sure the first extra join condition appears in the query.');
      $this->assertStringContainsString('"views_test_data"."status" = :db_condition_placeholder_14', $condition->__toString(), 'Make sure the second extra join condition appears in the query.');
      $this->assertStringContainsString('"users5"."name" = "views_test_data"."name"', $condition->__toString(), 'Make sure the third extra join condition appears in the query.');
      $this->assertEquals(['en', 0], array_values($condition->arguments()), 'Make sure the arguments are in the right order');
    }

    // Test that joins using 'left_formula' are properly built.
    $configuration['left_formula'] = 'MAX(views_test_data.uid)';
    // When 'left_formula' is present, 'left_field' is no longer required.
    unset($configuration['left_field']);
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users6'];
    // SQL strings are not supported by MongoDB.
    if (\Drupal::database()->driver() != 'mongodb') {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users6'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertStringContainsString("MAX(views_test_data.uid) = users6.uid", $condition->__toString(), 'Make sure the join condition appears in the query.');
      $this->assertStringContainsString('"users6"."langcode" = :db_condition_placeholder_18', $condition->__toString(), 'Make sure the first extra join condition appears in the query.');
      $this->assertStringContainsString('"views_test_data"."status" = :db_condition_placeholder_19', $condition->__toString(), 'Make sure the second extra join condition appears in the query.');
      $this->assertStringContainsString('"users6"."name" = "views_test_data"."name"', $condition->__toString(), 'Make sure the third extra join condition appears in the query.');
      $this->assertEquals(['en', 0], array_values($condition->arguments()), 'Make sure the arguments are in the right order');
    }

    $configuration = [
      'left_table' => 'views_test_data',
      'left_field' => 'uid',
      'table' => 'users_field_data',
      'field' => 'uid',
      'adjusted' => TRUE,
      'operator' => '<>',
    ];
    $join = $this->manager->createInstance('standard', $configuration);
    $table = ['alias' => 'users_field_data'];
    $query = Database::getConnection()->select('views_test_data');
    if (\Drupal::database()->driver() == 'mongodb') {
      $join->buildMongodbJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users_field_data'];
      $condition = $join_info['condition'];
      $condition->setMongodbJoinCondition();
      $condition->compile($connection, $query);
      $expected_condition = [
        '$expr' => [
          '$ne' => [
            '$views_test_data.uid',
            '$users_field_data.uid',
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $join->buildJoin($query, $table, $view->query);

      $tables = $query->getTables();
      $join_info = $tables['users_field_data'];
      $condition = $join_info['condition'];
      $condition->compile($connection, $query);
      $this->assertEquals('LEFT', $join_info['join type']);
      $this->assertEquals($configuration['table'], $join_info['table']);
      $this->assertEquals('users_field_data', $join_info['alias']);
      $this->assertEquals('"views_test_data"."uid" <> "users_field_data"."uid"', $condition->__toString());
    }
  }

}
