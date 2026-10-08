<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\Core\Database\DatabaseExceptionDumpAssertTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that a query failing behind the entity API still reports its statement.
 *
 * The SQL entity storage catches every exception a save raises and rethrows an
 * \Drupal\Core\Entity\EntityStorageException, which is not a database
 * exception. This is how most real query failures surface, so the statement
 * has to survive the wrapping.
 *
 * @see \Drupal\Core\Entity\Sql\SqlContentEntityStorage::doSave()
 * @see \Drupal\Core\Database\SqlExecutionInfo::fromThrowable()
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class EntityStorageExceptionDumpTest extends KernelTestBase {

  use DatabaseExceptionDumpAssertTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
  }

  /**
   * Tests the statement reported by an entity save that fails on the database.
   */
  public function testFailingEntitySaveReportsTheStatement(): void {
    // Add a column the storage knows nothing about, so that the insert the
    // save runs violates it. Taking the whole table away instead would not
    // fail in the same statement on every driver: PostgreSQL fails earlier, on
    // the catalog lookup its insert does to find the sequence of the table.
    $this->container->get('database')->schema()->addField('entity_test', 'must_be_set', [
      'type' => 'int',
      'not null' => TRUE,
    ]);
    $entity = EntityTest::create(['name' => 'Bananas are awesome']);

    try {
      $entity->save();
      $this->fail('Saving the entity did not throw.');
    }
    catch (EntityStorageException $e) {
      // The exception being reported is not a database exception itself; the
      // one carrying the statement is behind it.
      $this->assertNotInstanceOf(DatabaseException::class, $e);
      $this->assertInstanceOf(DatabaseException::class, $e->getPrevious());

      $info = $this->assertDump($e);
      // The statement the save ran is the one reported.
      $this->assertStringContainsString('INSERT INTO', $info->sqlString);
      $this->assertStringContainsString('entity_test', $info->sqlString);
      $this->assertContains('Bananas are awesome', $info->placeholders);
    }
  }

}
