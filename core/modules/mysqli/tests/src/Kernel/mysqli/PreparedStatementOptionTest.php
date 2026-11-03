<?php

declare(strict_types=1);

namespace Drupal\Tests\mysqli\Kernel\mysqli;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\Core\Database\DriverSpecificDatabaseTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests for the use of prepared statements in mysqli.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class PreparedStatementOptionTest extends DriverSpecificDatabaseTestBase {

  /**
   * Tests for the use of prepared statements in mysqli.
   *
   * @param bool $usePreparedStatements
   *   Whether or not to use prepared statements when querying.
   */
  #[TestWith([TRUE])]
  #[TestWith([FALSE])]
  public function testUsePreparedStatements(bool $usePreparedStatements): void {
    // Create an additional connection with the 'use_prepared_statements'
    // option set to our testing.
    $connectionInfo = Database::getConnectionInfo();
    $connectionInfo['default']['use_prepared_statements'] = $usePreparedStatements;
    Database::addConnectionInfo('default', 'test', $connectionInfo['default']);
    $testConnection = Database::getConnection('test');
    $this->assertSame($usePreparedStatements, $testConnection->getConnectionOptions()['use_prepared_statements']);

    // Taken below from InsertTest::testMultiInsert() to test non-trivial
    // queries.
    $num_records_before = (int) $testConnection->query('SELECT COUNT(*) FROM {test}')->fetchField();
    $query = $testConnection->insert('test');
    $query->fields(['name' => 'Larry', 'age' => '30']);
    $query->values(['age' => '31', 'name' => 'Curly']);
    $this->assertCount(2, $query);
    $query->values(['Moe', '32']);
    $this->assertCount(3, $query);
    $query->execute();
    $num_records_after = (int) $testConnection->query('SELECT COUNT(*) FROM {test}')->fetchField();
    $this->assertSame($num_records_before + 3, $num_records_after);
    $saved_age = $testConnection->query('SELECT [age] FROM {test} WHERE [name] = :name', [':name' => 'Larry'])->fetchField();
    $this->assertSame('30', $saved_age);
    $saved_age = $testConnection->query('SELECT [age] FROM {test} WHERE [name] = :name', [':name' => 'Curly'])->fetchField();
    $this->assertSame('31', $saved_age);
    $saved_age = $testConnection->query('SELECT [age] FROM {test} WHERE [name] = :name', [':name' => 'Moe'])->fetchField();
    $this->assertSame('32', $saved_age);
  }

}
