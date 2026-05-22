<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\KernelTests\Core\Database\DatabaseTestBase;

/**
 * Tests MongoDB table aliased fields usage.
 *
 * @group MongoDB
 */
class AliasedFieldsTest extends DatabaseTestBase {

  /**
   * Tests select query with aliased fields.
   */
  public function testSelectAliasedFields() {
    $connection = $this->container->get('database');

    $query = $connection->select('test');
    $query->addField('test', 'name', 'aliased_name');
    $query->addField('test', 'age', 'aliased_age');
    $query->addField('test', 'job');
    $records = $query->execute()->fetchAll();

    $this->assertEquals(count($records), 4, 'Returned the correct number of rows.');
    foreach ($records as $record) {
      $this->assertTrue(in_array($record->aliased_name, ['John', 'George', 'Ringo', 'Paul']), 'Fetched name is correct.');
      $this->assertFalse(isset($record->name), 'The name field is not set.');
      $this->assertTrue(in_array($record->aliased_age, [25, 26, 27, 28]), 'Fetched age is correct.');
      $this->assertFalse(isset($record->age), 'The age field is not set.');
      $this->assertTrue(in_array($record->job, ['Singer', 'Drummer', 'Songwriter']), 'Fetched job is correct.');
    }
  }

  /**
   * Tests select query with aliased fields and ordered by aliased field.
   */
  public function testSelectAliasedFieldsWithOrderByName() {
    $connection = $this->container->get('database');

    $query = $connection->select('test');
    $query->addField('test', 'name', 'aliased_name');
    $query->addField('test', 'age', 'aliased_age');
    $query->addField('test', 'job');
    $query->orderBy('aliased_name');
    $records = $query->execute()->fetchAll();

    $this->assertEquals(count($records), 4, 'Returned the correct number of rows.');
    $this->assertEquals($records[0]->aliased_name, 'George', 'Fetched name is correct.');
    $this->assertEquals($records[0]->aliased_age, 27, 'Fetched age is correct.');
    $this->assertEquals($records[1]->aliased_name, 'John', 'Fetched name is correct.');
    $this->assertEquals($records[1]->aliased_age, 25, 'Fetched age is correct.');
    $this->assertEquals($records[2]->aliased_name, 'Paul', 'Fetched name is correct.');
    $this->assertEquals($records[2]->aliased_age, 26, 'Fetched age is correct.');
    $this->assertEquals($records[3]->aliased_name, 'Ringo', 'Fetched name is correct.');
    $this->assertEquals($records[3]->aliased_age, 28, 'Fetched age is correct.');
  }

  /**
   * Tests select query with aliased fields and ordered descending by field.
   */
  public function testSelectAliasedFieldsWithOrderByAge() {
    $connection = $this->container->get('database');

    $query = $connection->select('test');
    $query->addField('test', 'name', 'aliased_name');
    $query->addField('test', 'age', 'aliased_age');
    $query->addField('test', 'job');
    $query->orderBy('aliased_age', 'DESC');
    $records = $query->execute()->fetchAll();

    $this->assertEquals(count($records), 4, 'Returned the correct number of rows.');
    $this->assertEquals($records[0]->aliased_name, 'Ringo', 'Fetched name is correct.');
    $this->assertEquals($records[0]->aliased_age, 28, 'Fetched age is correct.');
    $this->assertEquals($records[1]->aliased_name, 'George', 'Fetched name is correct.');
    $this->assertEquals($records[1]->aliased_age, 27, 'Fetched age is correct.');
    $this->assertEquals($records[2]->aliased_name, 'Paul', 'Fetched name is correct.');
    $this->assertEquals($records[2]->aliased_age, 26, 'Fetched age is correct.');
    $this->assertEquals($records[3]->aliased_name, 'John', 'Fetched name is correct.');
    $this->assertEquals($records[3]->aliased_age, 25, 'Fetched age is correct.');
  }

  /**
   * Tests select query with aliased fields and grouped and ordered by field.
   */
  public function testSelectAliasedFieldsWithGroupBy() {
    $connection = $this->container->get('database');

    $query = $connection->select('test');
    $query->addField('test', 'job', 'aliased_job');
    $query->groupBy('aliased_job');
    $query->orderBy('aliased_job', 'DESC');
    $records = $query->execute()->fetchAll();

    $this->assertEquals(count($records), 3, 'Returned the correct number of rows.');
    $this->assertEquals($records[0]->aliased_job, 'Songwriter', 'Fetched job is correct.');
    $this->assertEquals($records[1]->aliased_job, 'Singer', 'Fetched job is correct.');
    $this->assertEquals($records[2]->aliased_job, 'Drummer', 'Fetched job is correct.');
  }

}
