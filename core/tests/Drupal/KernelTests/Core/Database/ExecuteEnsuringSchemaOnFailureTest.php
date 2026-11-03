<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Database;

use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\Exception\SchemaCreationFailureException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Connection::executeEnsuringSchemaOnFailure().
 */
#[Group('Database')]
#[RunTestsInSeparateProcesses]
class ExecuteEnsuringSchemaOnFailureTest extends DatabaseTestBase {

  /**
   * Tests a valid callback when the schema already exists.
   */
  public function testValidCallbackOnExistingSchema(): void {
    // Create the tables before executing the test.
    $this->connection->schema()->createTable('fixture_header', $this->validMultiTableFixtureSchema()['fixture_header']);
    $this->connection->schema()->createTable('fixture_detail', $this->validMultiTableFixtureSchema()['fixture_detail']);

    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->insertIntoFixtureTables();
      },
      schema: $this->validMultiTableFixtureSchema(),
      retryAfterSchemaEnsured: TRUE,
    );

    // The schema was there so first callback execution was successful.
    $this->assertTrue($execution->isSuccessful());
    $this->assertTrue($execution->getCallbackExecutionState());
    $this->assertFalse($execution->getSchemaCreationState());
    $this->assertFalse($execution->getCallbackRetryExecutionState());
    $this->assertSame(1, $execution->getResult());

    $this->assertEquals(1, $this->connection->select('fixture_header', 'h')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('fixture_detail', 'h')->countQuery()->execute()->fetchField());
  }

  /**
   * Tests a valid callback on a missing schema, retried after creation.
   */
  public function testValidCallbackOnMissingMultiTableSchemaAndRetry(): void {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->insertIntoFixtureTables();
      },
      schema: $this->validMultiTableFixtureSchema(),
      retryAfterSchemaEnsured: TRUE,
    );

    // The initial callback execution failed, but the schema creation was
    // successful and the callback retry was successful too.
    $this->assertTrue($execution->isSuccessful());
    $this->assertInstanceOf(\Exception::class, $execution->getCallbackExecutionState());
    $this->assertTrue($execution->getSchemaCreationState());
    $this->assertTrue($execution->getCallbackRetryExecutionState());
    $this->assertSame(1, $execution->getResult());

    $this->assertEquals(1, $this->connection->select('fixture_header', 'h')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('fixture_detail', 'h')->countQuery()->execute()->fetchField());
  }

  /**
   * Tests a valid callback on a missing schema, not retried.
   */
  public function testValidCallbackOnMissingMultiTableSchemaNoRetry(): void {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->insertIntoFixtureTables();
      },
      schema: $this->validMultiTableFixtureSchema(),
    );

    // The initial callback execution failed, but the schema creation was
    // successful and the callback was not retried.
    $this->assertFalse($execution->isSuccessful());
    $this->assertInstanceOf(\Exception::class, $execution->getCallbackExecutionState());
    $this->assertTrue($execution->getSchemaCreationState());
    $this->assertFalse($execution->getCallbackRetryExecutionState());

    $this->assertEquals(0, $this->connection->select('fixture_header', 'h')->countQuery()->execute()->fetchField());
    $this->assertEquals(0, $this->connection->select('fixture_detail', 'h')->countQuery()->execute()->fetchField());

    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage("Drupal\Core\Database\Event\ExecuteMethodEnsuringSchemaEvent::getResult() was called before successful execution of the callback");
    $execution->getResult();
  }

  /**
   * Tests a broken callback when the schema already exists.
   */
  public function testBrokenCallbackOnExistingSchema(): void {
    $this->connection->schema()->createTable('fixture_header', $this->validMultiTableFixtureSchema()['fixture_header']);
    $this->connection->schema()->createTable('fixture_detail', $this->validMultiTableFixtureSchema()['fixture_detail']);

    // The schema is there already, so callback execution just throws an
    // exception, which is propagated through to the caller of
    // ::executeEnsuringSchemaOnFailure().
    $this->expectException(DatabaseExceptionWrapper::class);

    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->connection->query('bananas')->execute()->fetchField();
      },
      schema: $this->validMultiTableFixtureSchema(),
    );
  }

  /**
   * Tests a broken callback on a missing schema, retried after creation.
   */
  public function testBrokenCallbackOnMissingMultiTableSchemaAndRetry(): void {
    $this->assertFalse($this->connection->schema()->tableExists('fixture_header'));
    $this->assertFalse($this->connection->schema()->tableExists('fixture_detail'));

    try {
      $this->connection->executeEnsuringSchemaOnFailure(
        execute: function (): int {
          return $this->connection->query('bananas')->execute()->fetchField();
        },
        schema: $this->validMultiTableFixtureSchema(),
        retryAfterSchemaEnsured: TRUE,
      );
      $this->fail('Exception was expected.');
    }
    catch (\Exception $e) {
      $this->assertInstanceOf(DatabaseException::class, $e);
    }

    // Both callback executions failed, but the schema creation was successful.
    $this->assertTrue($this->connection->schema()->tableExists('fixture_header'));
    $this->assertTrue($this->connection->schema()->tableExists('fixture_detail'));
  }

  /**
   * Tests a broken callback on a missing schema, not retried.
   */
  public function testBrokenCallbackOnMissingMultiTableSchemaNoRetry(): void {
    $this->assertFalse($this->connection->schema()->tableExists('fixture_header'));
    $this->assertFalse($this->connection->schema()->tableExists('fixture_detail'));

    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->connection->query('bananas')->execute()->fetchField();
      },
      schema: $this->validMultiTableFixtureSchema(),
    );

    // The initial callback execution failed, but the schema creation was
    // successful and the callback was not retried.
    $this->assertFalse($execution->isSuccessful());
    $this->assertInstanceOf(\Exception::class, $execution->getCallbackExecutionState());
    $this->assertTrue($execution->getSchemaCreationState());
    $this->assertFalse($execution->getCallbackRetryExecutionState());

    $this->assertTrue($this->connection->schema()->tableExists('fixture_header'));
    $this->assertTrue($this->connection->schema()->tableExists('fixture_detail'));

    $this->expectException(\AssertionError::class);
    $this->expectExceptionMessage("Drupal\Core\Database\Event\ExecuteMethodEnsuringSchemaEvent::getResult() was called before successful execution of the callback");
    $execution->getResult();
  }

  /**
   * Tests a callback fetching a missing field from an existing schema.
   */
  public function testCallbackFetchingMissingFieldOnExistingSchema(): void {
    // Create the tables before executing the test.
    $this->connection->schema()->createTable('fixture_header', $this->validMultiTableFixtureSchema()['fixture_header']);
    $this->connection->schema()->createTable('fixture_detail', $this->validMultiTableFixtureSchema()['fixture_detail']);

    // The schema is there already, so callback execution just throws an
    // exception, which is propagated through to the caller of
    // ::executeEnsuringSchemaOnFailure().
    $this->expectException(DatabaseExceptionWrapper::class);

    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        $query = $this->connection->select('fixture_header', 'h');
        $query->fields('h', ['qux']);
        return (int) $query->execute()->fetchField();
      },
      schema: $this->validMultiTableFixtureSchema(),
    );
  }

  /**
   * Tests a valid callback with a successful schema callback.
   */
  public function testValidCallbackOnSuccessfulSchemaCallback(): void {
    $execution = $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->insertIntoFixtureTables();
      },
      schema: function (): bool {
        $this->connection->schema()->createTable('fixture_header', $this->validMultiTableFixtureSchema()['fixture_header']);
        $this->connection->schema()->createTable('fixture_detail', $this->validMultiTableFixtureSchema()['fixture_detail']);
        return TRUE;
      },
      retryAfterSchemaEnsured: TRUE,
    );

    // The initial callback execution failed, but the schema creation was
    // successful and the callback retry was successful too.
    $this->assertTrue($execution->isSuccessful());
    $this->assertInstanceOf(\Exception::class, $execution->getCallbackExecutionState());
    $this->assertTrue($execution->getSchemaCreationState());
    $this->assertTrue($execution->getCallbackRetryExecutionState());
    $this->assertSame(1, $execution->getResult());

    $this->assertEquals(1, $this->connection->select('fixture_header', 'h')->countQuery()->execute()->fetchField());
    $this->assertEquals(1, $this->connection->select('fixture_detail', 'h')->countQuery()->execute()->fetchField());
  }

  /**
   * Tests a valid callback with a failing schema callback.
   */
  public function testValidCallbackOnBrokenSchemaCallback(): void {
    // The initial callback execution failed, and the schema creation was
    // unsuccessful too. We expect an exception reporting the failed schema
    // operation.
    $this->expectException(SchemaCreationFailureException::class);
    $this->expectExceptionMessage("Schema creation callback failed");

    $this->connection->executeEnsuringSchemaOnFailure(
      execute: function (): int {
        return $this->insertIntoFixtureTables();
      },
      schema: function (): bool {
        $this->connection->query('CREATE BANANAS ON THE TREE')->execute();
        return TRUE;
      },
      retryAfterSchemaEnsured: TRUE,
    );
  }

  /**
   * Returns the schema definition of the fixture tables.
   *
   * @return array<string,array<string,mixed>>
   *   The schema definition, keyed by table name.
   */
  protected function validMultiTableFixtureSchema(): array {
    return [
      'fixture_header' => [
        'fields' => [
          'hid' => [
            'type' => 'serial',
            'not null' => TRUE,
            'description' => 'Primary Key: An ID.',
          ],
          'message' => [
            'type' => 'text',
            'not null' => TRUE,
            'description' => 'A message for the header.',
          ],
        ],
        'primary key' => ['hid'],
      ],
      'fixture_detail' => [
        'fields' => [
          'hid' => [
            'type' => 'int',
            'not null' => TRUE,
            'description' => 'The header ID.',
          ],
          'did' => [
            'type' => 'serial',
            'not null' => TRUE,
            'description' => 'Primary Key: An ID.',
          ],
          'message' => [
            'type' => 'text',
            'not null' => TRUE,
            'description' => 'A message for the detail.',
          ],
        ],
        'primary key' => ['did'],
        'indexes' => [
          'hid_did' => ['hid', 'did'],
        ],
      ],
    ];
  }

  /**
   * Inserts rows into the fixture tables.
   *
   * @return int
   *   The ID of the inserted fixture header row.
   */
  protected function insertIntoFixtureTables(): int {
    $hid = (int) $this->connection->insert('fixture_header')
      ->fields([
        'message' => 'foo',
      ])
      ->execute();

    $this->connection->insert('fixture_detail')
      ->fields([
        'hid' => $hid,
        'message' => 'bar',
      ])
      ->execute();

    return $hid;
  }

}
