<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Kernel\mysql;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificKernelTestBase;

/**
 * Tests row format option for the MySQL driver.
 *
 * @group Database
 */
class RowFormatTest extends DriverSpecificKernelTestBase {

  /**
   * Tests MySQL row format.
   *
   * @param string $rowFormat
   *   The row format to test.
   *
   * @dataProvider rowFormatProvider
   */
  public function testRowFormat(string $rowFormat): void {
    $tableName = "test_row_format_{$rowFormat}";
    $tableSpecification = [
      'fields' => [
        'id'  => [
          'type' => 'int',
          'default' => NULL,
        ],
      ],
    ];

    $connectionInfo = Database::getConnectionInfo()['default'];
    $connectionInfo['row_format'] = $rowFormat;

    Database::addConnectionInfo('row_format_test', 'default', $connectionInfo);
    Database::getConnection('default', 'row_format_test')->schema()->createTable($tableName, $tableSpecification);

    $result = $this->connection->query('SELECT `ROW_FORMAT` FROM INFORMATION_SCHEMA.TABLES WHERE `TABLE_SCHEMA` = :schema AND `TABLE_NAME` = :table', [
      ':schema' => $connectionInfo['database'],
      ':table' => $connectionInfo['prefix'] . $tableName,
    ])->fetchField();

    $this->assertEqualsIgnoringCase($rowFormat, $result, "{$tableName} should have row format {$rowFormat}, but it has not.");
  }

  /**
   * Data provider for testRowFormat().
   *
   * @return array
   */
  public static function rowFormatProvider(): array {
    return [
      'row format redundant' => ['REDUNDANT'],
      'row format compact' => ['COMPACT'],
      'row format dynamic' => ['DYNAMIC'],
      'row format compressed' => ['COMPRESSED'],
    ];
  }

}
