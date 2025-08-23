<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the SqlBase class.
 *
 * @group migrate
 */
class SqlBaseTest extends UnitTestCase {

  /**
   * The default configuration array.
   *
   * @var array
   */
  protected $configuration = [];

  /**
   * The default plugin_id string.
   *
   * @var string
   */
  protected $pluginId = '';

  /**
   * The default plugin_definition array.
   *
   * @var array
   */
  protected $pluginDefinition = [];

  /**
   * Tests that source conditions are recognized.
   *
   * @param array $configuration
   *   Source configuration.
   * @param string $message
   *   The expected exception message.
   *
   * @dataProvider sqlBaseConstructorTestProvider
   */
  public function testConstructor(array $configuration, string $message): void {
    // Setup the migration interface.
    $migration = $this->getMockBuilder(MigrationInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Setup the state object.
    $state = $this->getMockBuilder(StateInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Test with an invalid process pipeline.
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($message);
    new TestSqlBase($configuration, $this->pluginId, $this->pluginDefinition, $migration, $state);
  }

  /**
   * The data provider for testConstructor.
   */
  public static function sqlBaseConstructorTestProvider(): array {
    return [
      'not array' => [
        'configuration' => [
          'conditions' => [''],
        ],
        'message' => 'Each \'conditions\' array item must be an array including field, value (optional), and operator (optional) keys.',
      ],
      'not multidimensional array' => [
        'configuration' => [
          'conditions' => [''],
        ],
        'message' => 'Each \'conditions\' array item must be an array including field, value (optional), and operator (optional) keys.',
      ],
      'condition field not specified' => [
        'configuration' => [
          'conditions' => [
            ['value' => 'John', 'operator' => '='],
          ],
        ],
        'message' => 'Each \'conditions\' array item must be an array including field, value (optional), and operator (optional) keys.',
      ],
      'join table not specified' => [
        'configuration' => [
          'joins' => [
            ['alias' => 'u', 'condition' => 'u.uid=n.uid'],
          ],
        ],
        'message' => 'Each \'joins\' array item must be an array including table, alias, condition, and type (optional) keys.',
      ],
      'join alias not specified' => [
        'configuration' => [
          'joins' => [
            ['table' => 'users_field_data', 'condition' => 'u.uid=n.uid'],
          ],
        ],
        'message' => 'Each \'joins\' array item must be an array including table, alias, condition, and type (optional) keys.',
      ],
      'join condition not specified' => [
        'configuration' => [
          'joins' => [
            ['alias' => 'u', 'table' => 'users_field_data'],
          ],
        ],
        'message' => 'Each \'joins\' array item must be an array including table, alias, condition, and type (optional) keys.',
      ],
      'fields table_alias not specified' => [
        'configuration' => [
          'fields' => [
            ['field' => 'uid'],
          ],
        ],
        'message' => 'Each \'fields\' array item must be an array including table_alias, field, alias (optional) keys.',
      ],
      'fields field not specified' => [
        'configuration' => [
          'fields' => [
            ['table_alias' => 'u'],
          ],
        ],
        'message' => 'Each \'fields\' array item must be an array including table_alias, field, alias (optional) keys.',
      ],
    ];
  }

  /**
   * Tests that the ID map is joinable.
   *
   * @param bool $expected_result
   *   The expected result.
   * @param bool $id_map_is_sql
   *   TRUE if we want getIdMap() to return an instance of Sql.
   * @param bool $with_id_map
   *   TRUE if we want the ID map to have a valid map of IDs.
   * @param array $source_options
   *   (optional) An array of connection options for the source connection.
   *   Defaults to an empty array.
   * @param array $id_map_options
   *   (optional) An array of connection options for the ID map connection.
   *   Defaults to an empty array.
   *
   * @dataProvider sqlBaseTestProvider
   */
  public function testMapJoinable($expected_result, $id_map_is_sql, $with_id_map, $source_options = [], $id_map_options = []): void {
    // Setup a connection object.
    $source_connection = $this->getMockBuilder('Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();
    $source_connection->expects($id_map_is_sql && $with_id_map ? $this->once() : $this->never())
      ->method('getConnectionOptions')
      ->willReturn($source_options);

    // Setup the ID map connection.
    $id_map_connection = $this->getMockBuilder('Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();
    $id_map_connection->expects($id_map_is_sql && $with_id_map ? $this->once() : $this->never())
      ->method('getConnectionOptions')
      ->willReturn($id_map_options);

    // Setup the Sql object.
    $sql = $this->getMockBuilder('Drupal\migrate\Plugin\migrate\id_map\Sql')
      ->disableOriginalConstructor()
      ->getMock();
    $sql->expects($id_map_is_sql && $with_id_map ? $this->once() : $this->never())
      ->method('getDatabase')
      ->willReturn($id_map_connection);

    // Setup the State object.
    $state = $this->getMockBuilder(StateInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    // Setup a migration entity.
    $migration = $this->createMock(MigrationInterface::class);
    $migration->expects($this->atLeastOnce())
      ->method('getIdMap')
      ->willReturn($id_map_is_sql ? $sql : NULL);

    // Create our SqlBase test class.
    $sql_base = new TestSqlBase($this->configuration, $this->pluginId, $this->pluginDefinition, $migration, $state);
    $sql_base->setMigration($migration);
    $sql_base->setDatabase($source_connection);

    // Configure the idMap to make the check in mapJoinable() pass.
    if ($with_id_map) {
      $sql_base->setIds([
        'uid' => ['type' => 'integer', 'alias' => 'u'],
      ]);
    }

    $this->assertEquals($expected_result, $sql_base->mapJoinable());
  }

  /**
   * The data provider for SqlBase.
   *
   * @return array
   *   An array of data per test run.
   */
  public static function sqlBaseTestProvider() {
    return [
      // Source ids are empty so mapJoinable() is false.
      [
        FALSE,
        FALSE,
        FALSE,
      ],
      // Still false because getIdMap() is not a subclass of Sql.
      [
        FALSE,
        FALSE,
        TRUE,
      ],
      // Test mapJoinable() returns false when source and id connection options
      // differ.
      [
        FALSE,
        TRUE,
        TRUE,
        ['driver' => 'mysql', 'username' => 'different_from_map', 'password' => 'different_from_map'],
        ['driver' => 'mysql', 'username' => 'different_from_source', 'password' => 'different_from_source'],
      ],
      // Returns false because driver is pgsql and the databases are not the
      // same.
      [
        FALSE,
        TRUE,
        TRUE,
        ['driver' => 'pgsql', 'database' => '1.pgsql', 'username' => 'same_value', 'password' => 'same_value'],
        ['driver' => 'pgsql', 'database' => '2.pgsql', 'username' => 'same_value', 'password' => 'same_value'],
      ],
      // Returns false because driver is sqlite and the databases are not the
      // same.
      [
        FALSE,
        TRUE,
        TRUE,
        ['driver' => 'sqlite', 'database' => '1.sqlite', 'username' => '', 'password' => ''],
        ['driver' => 'sqlite', 'database' => '2.sqlite', 'username' => '', 'password' => ''],
      ],
      // Returns false because driver is not the same.
      [
        FALSE,
        TRUE,
        TRUE,
        ['driver' => 'pgsql', 'username' => 'same_value', 'password' => 'same_value'],
        ['driver' => 'mysql', 'username' => 'same_value', 'password' => 'same_value'],
      ],
    ];
  }

  /**
   * Test prepare query for valid condition.
   *
   * @param array $configuration
   *   Source configuration.
   * @param string $expected_result
   *   The expected result.
   *
   * @dataProvider prepareQueryTestProvider
   */
  public function testPrepareQuery(array $configuration, string $expected_result): void {
    $migration = $this->getMockBuilder(MigrationInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    // Setup the state object.
    $state = $this->getMockBuilder(StateInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $connection = $this->prophesize(Connection::class);
    $connection->condition(Argument::any())->willReturn(new Condition('AND'));
    $connection->getKey()->willReturn('default');
    $connection->getTarget()->willReturn('default');
    $connection->escapeField(Argument::any())->willReturnArgument(0);
    $connection->mapConditionOperator(Argument::any())->shouldBeCalled();
    $connection->makeComment(Argument::any())->shouldBeCalled();
    $connection->escapeTable(Argument::any())->willReturnArgument(0);
    $connection->escapeAlias(Argument::any())->willReturnArgument(0);
    $connection->select(Argument::any(), Argument::any(), Argument::any())->willReturn(new Select($connection->reveal(), 'users', 'u'));

    $sql = new TestSqlBase($configuration, $this->pluginId, $this->pluginDefinition, $migration, $state);
    $sql->setDatabase($connection->reveal());

    $this->assertEquals($expected_result, $sql->__toString());
  }

  /**
   * The data provider for testPrepareQuery.
   */
  public static function prepareQueryTestProvider(): array {
    return [
      'field value operator condition' => [
        'configuration' => [
          'conditions' => [
            [
              'field' => 'nid',
              'value' => '3',
              'operator' => '>',
            ],
          ],
        ],
        'expected_result' => "SELECT \nFROM\n{users} u\nWHERE nid > :db_condition_placeholder_0",
      ],
      'default operator condition' => [
        'configuration' => [
          'conditions' => [
            [
              'field' => 'type',
              'value' => 'article',
            ],
          ],
        ],
        'expected_result' => "SELECT \nFROM\n{users} u\nWHERE type = :db_condition_placeholder_0",
      ],
      'default value null condition' => [
        'configuration' => [
          'conditions' => [
            [
              'field' => 'langcode',
              'operator' => 'IS',
            ],
          ],
        ],
        'expected_result' => "SELECT \nFROM\n{users} u\nWHERE langcode IS :db_condition_placeholder_0",
      ],
      'field value operator multiple condition' => [
        'configuration' => [
          'conditions' => [
            [
              'field' => 'nid',
              'value' => '3',
              'operator' => '>',
            ],
            [
              'field' => 'title',
              'operator' => 'IS',
            ],
          ],
        ],
        'expected_result' => "SELECT \nFROM\n{users} u\nWHERE (nid > :db_condition_placeholder_0) AND (title IS :db_condition_placeholder_1)",
      ],
      'multiple sql conjunctions' => [
        'configuration' => [
          'conditions' => [
            [
              'field' => 'nid',
              'value' => '3',
              'operator' => '>',
            ],
            [
              'field' => 'title',
              'operator' => 'IS',
            ],
          ],
          'fields' => [
            ['table_alias' => 'ud', 'field' => 'data', 'alias' => 'd'],
          ],
          'joins' => [
            ['table' => 'users_field_data', 'alias' => 'ud', 'condition' => 'u.uid=n.uid'],
          ],
          'distinct' => TRUE,
        ],
        'expected_result' => "SELECT DISTINCT ud.data AS d\nFROM\n{users} u\nINNER JOIN {users_field_data} ud ON u.uid=n.uid\nWHERE (nid > :db_condition_placeholder_0) AND (title IS :db_condition_placeholder_1)",
      ],
    ];
  }

}

/**
 * Creates a base source class for SQL migration testing.
 */
class TestSqlBase extends SqlBase {

  /**
   * The database object.
   *
   * @var object
   */
  protected $database;

  /**
   * The migration IDs.
   *
   * @var array
   */
  protected $ids;

  /**
   * Allows us to set the database during tests.
   *
   * @param mixed $database
   *   The database mock object.
   */
  public function setDatabase($database): void {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function getDatabase() {
    return $this->database;
  }

  /**
   * Allows us to set the migration during the test.
   *
   * @param mixed $migration
   *   The migration mock.
   */
  public function setMigration($migration): void {
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public function mapJoinable() {
    return parent::mapJoinable();
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return $this->ids;
  }

  /**
   * Allows us to set the IDs during a test.
   *
   * @param array $ids
   *   An array of identifiers.
   */
  public function setIds($ids): void {
    $this->ids = $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    throw new \RuntimeException(__METHOD__ . " not implemented for " . __CLASS__);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u');
  }

  public function __toString() {
    return $this->prepareQuery()->__toString();
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

}
