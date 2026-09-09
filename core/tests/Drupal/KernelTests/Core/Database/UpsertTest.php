<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

// cspell:ignore Zaphod

use Drupal\Core\Database\Database;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Query\NoFieldsException;
use Drupal\Core\Database\Query\UpsertUpdateExpression;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Upsert query builder.
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class UpsertTest extends DatabaseTestBase {

  /**
   * Confirms that we can upsert (update-or-insert) records successfully.
   */
  public function testUpsert(): void {
    $connection = Database::getConnection();
    $num_records_before = $connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField();

    $upsert = $connection->upsert('test_people')
      ->key('job')
      ->fields(['job', 'age', 'name']);

    // Add a new row.
    $upsert->values([
      'job' => 'Presenter',
      'age' => 31,
      'name' => 'Tiffany',
    ]);

    // Update an existing row.
    $upsert->values([
      'job' => 'Speaker',
      // The initial age was 30.
      'age' => 32,
      'name' => 'Meredith',
    ]);

    $result = $upsert->execute();
    $this->assertIsInt($result);
    $this->assertGreaterThanOrEqual(2, $result, 'The result of the upsert operation should report that at least two rows were affected.');

    $num_records_after = $connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField();
    $this->assertEquals($num_records_before + 1, $num_records_after, 'Rows were inserted and updated properly.');

    $person = $connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Presenter'])->fetch();
    $this->assertEquals('Presenter', $person->job, 'Job set correctly.');
    $this->assertEquals(31, $person->age, 'Age set correctly.');
    $this->assertEquals('Tiffany', $person->name, 'Name set correctly.');

    $person = $connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Speaker'])->fetch();
    $this->assertEquals('Speaker', $person->job, 'Job was not changed.');
    $this->assertEquals(32, $person->age, 'Age updated correctly.');
    $this->assertEquals('Meredith', $person->name, 'Name was not changed.');
  }

  /**
   * Confirms that we can upsert using UpsertUpdateExpression.
   */
  public function testUpsertWithUpsertUpdateExpression(): void {
    $this->assertSame('1', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    $upsert = $this->connection->upsert('test_people')
      ->key('job')
      ->fields([
        'job',
        // Use two placeholder arguments to cover the argument binding done by
        // each driver.
        'age' => new UpsertUpdateExpression('{test_people}.[age] + :one + :two', [':one' => 2, ':two' => 3]),
        'name',
      ]);

    // Insert a new row.
    $upsert->values([
      'job' => 'Presenter',
      'age' => 31,
      'name' => 'Tiffany',
    ]);

    // Update an existing row.
    $upsert->values([
      'job' => 'Speaker',
      // The initial age was 30; the new age should be 35 given the
      // UpsertUpdateExpression, and the value of 32 passed in discarded.
      'age' => 32,
      'name' => 'Meredith',
    ]);

    $upsert->execute();

    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Presenter'])->fetch();
    $this->assertSame('Presenter', $person->job);
    $this->assertSame('31', $person->age);
    $this->assertSame('Tiffany', $person->name);

    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Speaker'])->fetch();
    $this->assertSame('Speaker', $person->job);
    $this->assertSame('35', $person->age, 'Age should have been updated to 35.');
    $this->assertSame('Meredith', $person->name);

    // An associative $fields array cannot mix insert values with update
    // expressions, because the values cannot be told apart from column names.
    try {
      $this->connection->upsert('test_people')
        ->key('job')
        ->fields([
          'job' => 'Speaker',
          'age' => new UpsertUpdateExpression('{test_people}.[age] + 1'),
        ]);
      $this->fail('Expected exception when mixing insert values with an update expression.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('The entry for "job"', $e->getMessage());
    }

    // An update expression needs a column name key.
    try {
      $this->connection->upsert('test_people')
        ->key('job')
        ->fields([
          'job',
          new UpsertUpdateExpression('{test_people}.[age] + 1'),
        ]);
      $this->fail('Expected exception for an update expression without a column name key.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('must be keyed by its column name', $e->getMessage());
    }
  }

  /**
   * Confirms that an update condition can leave the existing record untouched.
   */
  public function testUpsertWithUpdateCondition(): void {
    // The only record is 'Speaker', aged 30, named 'Meredith'.
    $this->assertSame('1', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    // The columns are listed with the column the condition reads last, the
    // order MySQL and MariaDB need.
    $upsert = fn (int $below, int $age, string $name): int => $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job', 'name', 'age'])
      ->values(['Speaker', $name, $age])
      ->updateCondition('{test_people}.[age] < :below', [':below' => $below])
      ->execute();

    // The condition does not hold, so the record is left untouched. No driver
    // reports the record as affected.
    $this->assertSame(0, $upsert(30, 32, 'Tiffany'));
    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Speaker'])->fetch();
    $this->assertSame('30', $person->age);
    $this->assertSame('Meredith', $person->name);

    // The condition holds, so every column is updated.
    $this->assertGreaterThan(0, $upsert(31, 32, 'Tiffany'));
    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Speaker'])->fetch();
    $this->assertSame('32', $person->age);
    $this->assertSame('Tiffany', $person->name);

    // A record that does not exist yet is inserted, whatever the condition.
    $rows = $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job', 'name', 'age'])
      ->values(['Presenter', 'Zaphod', 31])
      ->updateCondition('{test_people}.[age] < :below', [':below' => 0])
      ->execute();
    $this->assertGreaterThan(0, $rows);
    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Presenter'])->fetch();
    $this->assertSame('31', $person->age);
    $this->assertSame('Zaphod', $person->name);

    // An update condition needs a column to update.
    $this->expectException(NoFieldsException::class);
    $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job'])
      ->values(['Speaker'])
      ->updateCondition('{test_people}.[age] < :below', [':below' => 99])
      ->execute();
  }

  /**
   * Confirms that an update condition can read a column that is updated.
   *
   * The condition is evaluated against the existing record, so a column it
   * reads holds its old value even when the same statement overwrites it.
   */
  public function testUpsertWithUpdateConditionOnUpdatedColumn(): void {
    $rows = $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job', 'age', 'name'])
      ->values(['Speaker', 32, 'Tiffany'])
      ->updateCondition('{test_people}.[age] < :below', [':below' => 31])
      ->execute();

    $this->assertGreaterThan(0, $rows);
    $person = $this->connection->query('SELECT * FROM {test_people} WHERE [job] = :job', [':job' => 'Speaker'])->fetch();
    $this->assertSame('32', $person->age);
    $this->assertSame('Tiffany', $person->name, 'Every column of the record is updated.');
  }

  /**
   * Confirms what an update condition cannot be combined with.
   */
  public function testUpsertWithUnsupportedUpdateCondition(): void {
    // More than one row of values.
    try {
      $this->connection->upsert('test_people')
        ->key('job')
        ->fields(['job', 'name', 'age'])
        ->values(['Speaker', 'Tiffany', 32])
        ->values(['Presenter', 'Zaphod', 31])
        ->updateCondition('{test_people}.[age] < :below', [':below' => 31])
        ->execute();
      $this->fail('Expected exception for an update condition on more than one row.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('only supported for a single row', $e->getMessage());
    }

    // A condition reading more than one of the updated columns.
    try {
      $this->connection->upsert('test_people')
        ->key('job')
        ->fields(['job', 'name', 'age'])
        ->values(['Speaker', 'Tiffany', 32])
        ->updateCondition('{test_people}.[age] < :below AND {test_people}.[name] <> :name', [
          ':below' => 31,
          ':name' => 'Tiffany',
        ])
        ->execute();
      $this->fail('Expected exception for an update condition reading two updated columns.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('may read at most one of the updated columns', $e->getMessage());
    }

    // Reading a column of the key is always supported: it is never updated.
    $rows = $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job', 'name', 'age'])
      ->values(['Speaker', 'Tiffany', 32])
      ->updateCondition('{test_people}.[job] = :job AND {test_people}.[age] < :below', [
        ':job' => 'Speaker',
        ':below' => 31,
      ])
      ->execute();
    $this->assertGreaterThan(0, $rows);

    // An update expression.
    try {
      $this->connection->upsert('test_people')
        ->key('job')
        ->fields(['job', 'name', 'age' => new UpsertUpdateExpression('{test_people}.[age] + 1')])
        ->values(['Speaker', 'Tiffany', 32])
        ->updateCondition('{test_people}.[age] < :below', [':below' => 31])
        ->execute();
      $this->fail('Expected exception for an update condition combined with an update expression.');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('cannot be combined with an update expression', $e->getMessage());
    }
  }

  /**
   * Confirms that an update condition works with a composite key.
   */
  public function testCompositeKeyUpsertWithUpdateCondition(): void {
    $this->installSchema('database_test', ['test_composite_primary']);
    $this->connection->insert('test_composite_primary')
      ->fields(['name', 'age', 'job'])
      ->values(['name' => 'Meredith', 'age' => 30, 'job' => 'Speaker'])
      ->execute();

    // The condition reads the only column that is updated, which is what a
    // condition on an expiration does.
    $upsert = fn (string $job, string $held_by): int => $this->connection->upsert('test_composite_primary')
      ->key(['name', 'age'])
      ->fields(['name' => 'Meredith', 'age' => 30, 'job' => $job])
      ->updateCondition('{test_composite_primary}.[job] = :job', [':job' => $held_by])
      ->execute();

    // The condition does not hold, so the record is left untouched.
    $this->assertSame(0, $upsert('Organizer', 'Presenter'));
    $person = $this->connection->query('SELECT * FROM {test_composite_primary} WHERE [name] = :name', [':name' => 'Meredith'])->fetch();
    $this->assertSame('Speaker', $person->job);

    // The condition holds, so the record is updated.
    $this->assertGreaterThan(0, $upsert('Organizer', 'Speaker'));
    $person = $this->connection->query('SELECT * FROM {test_composite_primary} WHERE [name] = :name', [':name' => 'Meredith'])->fetch();
    $this->assertSame('Organizer', $person->job);

    // A record that does not exist yet is inserted, whatever the condition.
    $rows = $this->connection->upsert('test_composite_primary')
      ->key(['name', 'age'])
      ->fields(['name' => 'Meredith', 'age' => 31, 'job' => 'Presenter'])
      ->updateCondition('{test_composite_primary}.[job] = :job', [':job' => 'nobody'])
      ->execute();
    $this->assertGreaterThan(0, $rows);
    $person = $this->connection->query('SELECT * FROM {test_composite_primary} WHERE [age] = :age', [':age' => 31])->fetch();
    $this->assertSame('Presenter', $person->job);
  }

  /**
   * Confirms that we can upsert records with keywords successfully.
   */
  public function testUpsertWithKeywords(): void {
    $num_records_before = $this->connection->query('SELECT COUNT(*) FROM {select}')->fetchField();

    $upsert = $this->connection->upsert('select')
      ->key('id')
      ->fields(['id', 'update']);

    // Add a new row.
    $upsert->values([
      // Test a non sequence ID for better testing of the default return value.
      'id' => 3,
      'update' => 'Update value 2',
    ]);

    // Update an existing row.
    $upsert->values([
      'id' => 1,
      'update' => 'Update value 1 updated',
    ]);

    $result = $upsert->execute();
    $this->assertIsInt($result);
    // The upsert returns the number of rows affected. For MySQL the return
    // value is 3 because the affected-rows value per row is 1 if the row is
    // inserted as a new row, 2 if an existing row is updated. See
    // https://dev.mysql.com/doc/c-api/8.0/en/mysql-affected-rows.html.
    $this->assertGreaterThanOrEqual(2, $result, 'The result of the upsert operation should report that at least two rows were affected.');

    $num_records_after = $this->connection->query('SELECT COUNT(*) FROM {select}')->fetchField();
    $this->assertEquals($num_records_before + 1, $num_records_after, 'Rows were inserted and updated properly.');

    $record = $this->connection->query('SELECT * FROM {select} WHERE [id] = :id', [':id' => 1])->fetch();
    $this->assertEquals('Update value 1 updated', $record->update);

    $record = $this->connection->query('SELECT * FROM {select} WHERE [id] = :id', [':id' => 3])->fetch();
    $this->assertEquals('Update value 2', $record->update);

    // An upsert should be re-usable.
    $upsert->values([
      'id' => 4,
      'update' => 'Another value',
    ]);
    $return_value = $upsert->execute();
    $this->assertSame(1, $return_value);
    $record = $this->connection->query('SELECT * FROM {select} WHERE [id] = :id', [':id' => 4])->fetch();
    $this->assertEquals('Another value', $record->update);
  }

  /**
   * Upsert on a not existing table throws a DatabaseExceptionWrapper.
   */
  public function testUpsertNonExistingTable(): void {
    $this->expectException(DatabaseExceptionWrapper::class);
    $upsert = $this->connection->upsert('a-table-that-does-not-exist')
      ->key('id')
      ->fields(['id', 'update']);
    $upsert->values([
      'id' => 1,
      'update' => 'Update value 1 updated',
    ]);
    $upsert->execute();
  }

  /**
   * Confirms that we can upsert records with composite keys successfully.
   */
  public function testCompositeKeyUpsert(): void {
    $connection = Database::getConnection();
    $this->installSchema('database_test', ['test_composite_primary']);

    // Add some initial test data.
    $connection->insert('test_composite_primary')
      ->fields(['name', 'age', 'job'])
      ->values([
        'name' => 'Tiffany',
        'age' => 31,
        'job' => 'Presenter',
      ])
      ->values([
        'name' => 'Meredith',
        'age' => 30,
        'job' => 'Speaker',
      ])
      ->execute();

    $num_records_before = $connection->query('SELECT COUNT(*) FROM {test_composite_primary}')->fetchField();

    $upsert = $connection->upsert('test_composite_primary')
      ->key(['name', 'age'])
      // Add a new row directly from ::fields().
      ->fields([
        'name' => 'Kate',
        'age' => 25,
        'job' => 'Volunteer',
      ]);

    // Add a new row.
    $upsert->values([
      'name' => 'Karen',
      'age' => 35,
      'job' => 'Manager',
    ]);

    // Update an existing row.
    $upsert->values([
      'name' => 'Meredith',
      'age' => 30,
      // The initial job was 'Speaker'.
      'job' => 'Organizer',
    ]);

    // Add a new row by reusing a name but with a different age. This won't
    // match the composite primary key constraint.
    $upsert->values([
      'name' => 'Meredith',
      'age' => 40,
      'job' => 'Supervisor',
    ]);

    $upsert->execute();

    $num_records_after = $connection->query('SELECT COUNT(*) FROM {test_composite_primary}')->fetchField();
    $this->assertEquals($num_records_before + 3, $num_records_after, 'Rows were inserted and updated properly.');

    $person = $connection->query('SELECT * FROM {test_composite_primary} WHERE [job] = :job', [':job' => 'Volunteer'])->fetch();
    $this->assertEquals('Volunteer', $person->job, 'Job set correctly.');
    $this->assertEquals(25, $person->age, 'Age set correctly.');
    $this->assertEquals('Kate', $person->name, 'Name set correctly.');

    $person = $connection->query('SELECT * FROM {test_composite_primary} WHERE [job] = :job', [':job' => 'Manager'])->fetch();
    $this->assertEquals('Manager', $person->job, 'Job set correctly.');
    $this->assertEquals(35, $person->age, 'Age set correctly.');
    $this->assertEquals('Karen', $person->name, 'Name set correctly.');

    $person = $connection->query('SELECT * FROM {test_composite_primary} WHERE [job] = :job', [':job' => 'Organizer'])->fetch();
    $this->assertEquals('Organizer', $person->job, 'Job set correctly.');
    $this->assertEquals(30, $person->age, 'Age set correctly.');
    $this->assertEquals('Meredith', $person->name, 'Name set correctly.');

    $person = $connection->query('SELECT * FROM {test_composite_primary} WHERE [job] = :job', [':job' => 'Supervisor'])->fetch();
    $this->assertEquals('Supervisor', $person->job, 'Job set correctly.');
    $this->assertEquals(40, $person->age, 'Age set correctly.');
    $this->assertEquals('Meredith', $person->name, 'Name set correctly.');
  }

  /**
   * Tests a single-value upsert that has no columns to update.
   */
  public function testDegeneratedSingleValueUpsert(): void {
    $this->assertSame('1', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    // An upsert that only adds a record.
    $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job'])
      ->values(['Plumber'])
      ->execute();

    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    // Do the same upsert, should be a no-op.
    $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job'])
      ->values(['Plumber'])
      ->execute();

    // No records added.
    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());
  }

  /**
   * Tests a multi-value upsert that has no columns to update.
   */
  public function testDegeneratedMultiValueUpsert(): void {
    $this->assertSame('1', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    // An upsert that only adds a record.
    $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job'])
      ->values(['Plumber'])
      ->execute();

    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());

    // Do a multi-value upsert that should add two more records.
    $this->connection->upsert('test_people')
      ->key('job')
      ->fields(['job'])
      ->values(['Plumber'])
      ->values(['Carpenter'])
      ->values(['GalacticHitchhiker'])
      ->execute();

    // Two records added, for a total of four.
    $this->assertSame('4', $this->connection->query('SELECT COUNT(*) FROM {test_people}')->fetchField());
  }

  /**
   * Tests an upsert with a composite key and no columns to update.
   */
  public function testDegeneratedCompositeKeyUpsert(): void {
    $this->installSchema('database_test', ['test_composite_primary']);

    // Insert two new rows.
    $this->connection->insert('test_composite_primary')
      ->fields([
        'name' => 'Kate',
        'age' => 25,
        'job' => 'Volunteer',
      ])
      ->values([
        'name' => 'Bertie',
        'age' => 6,
        'job' => 'Puppet',
      ])
      ->execute();

    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_composite_primary}')->fetchField());

    // Performing an upsert with the same composite key should be a no-op.
    $this->connection->upsert('test_composite_primary')
      ->key(['name', 'age'])
      // Add a new row directly from ::fields().
      ->fields([
        'name' => 'Kate',
        'age' => 25,
      ])
      ->values([
        'name' => 'Bertie',
        'age' => 6,
      ])
      ->execute();

    $this->assertSame('2', $this->connection->query('SELECT COUNT(*) FROM {test_composite_primary}')->fetchField());

    // Different composite keys should insert other rows.
    $this->connection->upsert('test_composite_primary')
      ->key(['name', 'age'])
      // Add a new row directly from ::fields().
      ->fields(['name', 'age'])
      ->values([
        'name' => 'Kate',
        'age' => 25,
      ])
      ->values([
        'name' => 'Arthur',
        'age' => 30,
      ])
      ->values([
        'name' => 'Bertie',
        'age' => 6,
      ])
      ->values([
        'name' => 'Zaphod',
        'age' => 200,
      ])
      ->execute();

    $this->assertSame('4', $this->connection->query('SELECT COUNT(*) FROM {test_composite_primary}')->fetchField());
  }

  /**
   * Tests that we can upsert a null into blob field.
   */
  public function testUpsertNullBlob(): void {
    $id = $this->connection->insert('test_one_blob')
      ->fields(['blob1' => 'test'])
      ->execute();
    $r = $this->connection->query('SELECT * FROM {test_one_blob} WHERE [id] = :id', [':id' => $id])->fetchAssoc();
    $this->assertSame('test', $r['blob1']);

    $this->connection->upsert('test_one_blob')
      ->key('id')
      ->fields(['id', 'blob1'])
      ->values(['id' => $id, 'blob1' => NULL])
      ->values(['id' => $id + 1, 'blob1' => NULL])
      ->execute();
    $r = $this->connection->query('SELECT * FROM {test_one_blob} WHERE [id] = :id', [':id' => $id])->fetchAssoc();
    $this->assertNull($r['blob1']);
    $r = $this->connection->query('SELECT * FROM {test_one_blob} WHERE [id] = :id', [':id' => $id + 1])->fetchAssoc();
    $this->assertNull($r['blob1']);
  }

}
