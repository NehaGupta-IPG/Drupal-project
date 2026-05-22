<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\views\Plugin\views\join\FieldOrLanguageJoin;
use Drupal\views\Views;
use Drupal\views\ViewExecutable;

/**
 * Tests the "field OR language" join plugin.
 *
 * @group views
 * @see \Drupal\views\Plugin\views\join\FieldOrLanguageJoin
 */
class FieldOrLanguageJoinTest extends RelationshipJoinTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_view'];

  /**
   * {@inheritdoc}
   */
  protected $pluginId = 'field_or_language_join';

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
   * Tests base join functionality.
   *
   * This duplicates parts of
   * \Drupal\Tests\views\Kernel\Plugin\JoinTest::testBasePlugin() to ensure that
   * no functionality provided by the base join plugin is broken.
   */
  public function testBase(): void {
    $driver = \Drupal::database()->driver();

    // Setup a simple join and test the result sql.
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();

    // First define a simple join without an extra condition.
    // Set the various options on the join object.
    $configuration = [
      'left_table' => 'views_test_data',
      'left_field' => 'uid',
      'table' => ($driver == 'mongodb' ? 'users' : 'users_field_data'),
      'field' => 'uid',
      'adjusted' => TRUE,
    ];
    $join = $this->manager->createInstance($this->pluginId, $configuration);
    $this->assertInstanceOf(FieldOrLanguageJoin::class, $join);
    $this->assertNull($join->extra);
    $this->assertTrue($join->adjusted);

    $join_info = $this->buildJoin($view, $configuration, 'users_field_data');
    $this->assertSame($join_info['join type'], 'LEFT');
    $this->assertSame($join_info['table'], $configuration['table']);
    $this->assertSame($join_info['alias'], 'users_field_data');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
      $expected_condition = [
        '$expr' => [
          '$eq' => [
            '$views_test_data.uid',
            '$users_field_data.uid',
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $this->assertSame($condition->__toString(), '"views_test_data"."uid" = "users_field_data"."uid"');
    }

    // Set a different alias and make sure table info is as expected.
    $join_info = $this->buildJoin($view, $configuration, 'users1');
    $this->assertSame($join_info['alias'], 'users1');

    // Set a different join type (INNER) and make sure it is used.
    $configuration['type'] = 'INNER';
    $join_info = $this->buildJoin($view, $configuration, 'users2');
    $this->assertSame($join_info['join type'], 'INNER');

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
    $join_info = $this->buildJoin($view, $configuration, 'users3');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
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
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame(array_values($condition->arguments()), []);
    }
    else {
      $this->assertStringContainsString('"views_test_data"."uid" = "users3"."uid"', $condition->__toString());
      $this->assertStringContainsString('"users3"."name" = :db_condition_placeholder_0', $condition->__toString());
      $this->assertStringContainsString('"users3"."name" <> :db_condition_placeholder_1', $condition->__toString());
      $this->assertSame(array_values($condition->arguments()), [
        $random_name_1,
        $random_name_2,
      ]);
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
    $join_info = $this->buildJoin($view, $configuration, 'users4');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
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
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
      $this->assertSame(array_values($condition->arguments()), []);
    }
    else {
      $this->assertStringContainsString('"views_test_data"."uid" = "users4"."uid"', $condition->__toString());
      $this->assertStringContainsString('"users4"."name" = :db_condition_placeholder_0', $condition->__toString());
      $this->assertStringContainsString('"users4"."name" IN (:db_condition_placeholder_1, :db_condition_placeholder_2, :db_condition_placeholder_3)', $condition->__toString());
      $this->assertSame(array_values($condition->arguments()), [
        $random_name_1,
        $random_name_2,
        $random_name_3,
        $random_name_4,
      ]);
    }
  }

  /**
   * Tests the adding of conditions by the join plugin.
   */
  public function testLanguageBundleConditions(): void {
    $driver = \Drupal::database()->driver();

    // Setup a simple join and test the result sql.
    $view = Views::getView('test_view');
    $view->initDisplay();
    $view->initQuery();

    // Set the various options on the join object with only a langcode
    // condition.
    $configuration = [
      'table' => 'node__field_tags',
      'left_table' => 'views_test_data',
      'left_field' => 'nid',
      'field' => 'entity_id',
      'extra' => [
        [
          'left_field' => 'langcode',
          'field' => 'langcode',
        ],
      ],
    ];
    $join_info = $this->buildJoin($view, $configuration, 'node__field_tags');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.nid',
                '$node__field_tags.entity_id',
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.langcode',
                '$views_test_data.langcode',
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $this->assertStringContainsString('AND ("node__field_tags"."langcode" = "views_test_data"."langcode")', $condition->__toString());
    }

    array_unshift($configuration['extra'], [
      'field' => 'deleted',
      'value' => 0,
      'numeric' => TRUE,
    ]);
    $join_info = $this->buildJoin($view, $configuration, 'node__field_tags');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.nid',
                '$node__field_tags.entity_id',
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.deleted',
                0,
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.langcode',
                '$views_test_data.langcode',
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $this->assertStringContainsString('AND ("node__field_tags"."langcode" = "views_test_data"."langcode")', $condition->__toString());
    }

    // Replace the language condition with a bundle condition.
    $configuration['extra'][1] = [
      'field' => 'bundle',
      'value' => ['page'],
    ];
    $join_info = $this->buildJoin($view, $configuration, 'node__field_tags');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.nid',
                '$node__field_tags.entity_id',
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.deleted',
                0,
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.bundle',
                'page',
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $this->assertStringContainsString('AND ("node__field_tags"."bundle" = :db_condition_placeholder_1)', $condition->__toString());
    }

    // Now re-add a language condition to make sure the bundle and language
    // conditions are combined with an OR.
    $configuration['extra'][] = [
      'left_field' => 'langcode',
      'field' => 'langcode',
    ];
    $join_info = $this->buildJoin($view, $configuration, 'node__field_tags');
    $condition = $join_info['condition'];
    if ($driver == 'mongodb') {
      $condition->setMongodbJoinCondition();
    }
    $condition->compile($view->getQuery()->getConnection(), $view->getQuery()->query());
    if ($driver == 'mongodb') {
      $expected_condition = [
        '$and' => [
          [
            '$expr' => [
              '$eq' => [
                '$views_test_data.nid',
                '$node__field_tags.entity_id',
              ],
            ],
          ],
          [
            '$expr' => [
              '$eq' => [
                '$node__field_tags.deleted',
                0,
              ],
            ],
          ],
          [
            '$or' => [
              [
                '$expr' => [
                  '$eq' => [
                    '$node__field_tags.bundle',
                    'page',
                  ],
                ],
              ],
              [
                '$expr' => [
                  '$eq' => [
                    '$node__field_tags.langcode',
                    '$views_test_data.langcode',
                  ],
                ],
              ],
            ],
          ],
        ],
      ];
      $this->assertSame($condition->toMongoAggregateArray(), $expected_condition);
    }
    else {
      $this->assertStringContainsString('AND (("node__field_tags"."bundle" = :db_condition_placeholder_1) OR ("node__field_tags"."langcode" = "views_test_data"."langcode"))', $condition->__toString());
    }
  }

  /**
   * Builds a join using the given configuration.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view used in this test.
   * @param $configuration
   *   The join plugin configuration.
   * @param $table_alias
   *   The table alias to use for the join.
   *
   * @return array
   *   The join information for the joined table. See
   *   \Drupal\Core\Database\Query\Select::$tables for more information on the
   *   structure of the array.
   */
  protected function buildJoin(ViewExecutable $view, $configuration, $table_alias) {
    // Build the actual join values and read them back from the query object.
    $query = \Drupal::database()->select('node');

    $join = $this->manager->createInstance('field_or_language_join', $configuration);
    $this->assertInstanceOf(FieldOrLanguageJoin::class, $join);

    $table = ['alias' => $table_alias];
    $join->buildJoin($query, $table, $view->query);

    $tables = $query->getTables();
    $join_info = $tables[$table['alias']];
    return $join_info;
  }

}
