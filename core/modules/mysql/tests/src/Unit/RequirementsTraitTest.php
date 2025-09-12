<?php

declare(strict_types=1);

namespace Drupal\Tests\mysql\Unit;

use Drupal\Core\Database\Schema;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\mysql\RequirementsTrait;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests MySQL requirements trait.
 *
 * @coversDefaultClass \Drupal\mysql\RequirementsTrait
 * @group mysql
 */
class RequirementsTraitTest extends UnitTestCase {

  /**
   * A mocked MySQL connection.
   *
   * @var \Drupal\Core\Database\Connection&\PHPUnit\Framework\MockObject\MockObject
   */
  private Connection&MockObject $connection;

  /**
   * A mocked schema object.
   *
   * @var \Drupal\Core\Database\Schema&\PHPUnit\Framework\MockObject\MockObject
   */
  private Schema&MockObject $schema;

  /**
   * Test subject that uses RequirementsTrait.
   *
   * @var object
   */
  private object $requirementsTraitObject;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection = $this->createMock(Connection::class);
    $this->schema = $this->createMock(Schema::class);

    $translation = $this->getStringTranslationStub();

    // Create an anonymous class that uses the trait for testing.
    $this->requirementsTraitObject = new class() {
      use RequirementsTrait;

      /**
       * The string translation service.
       *
       * @var \Drupal\Core\StringTranslation\TranslationInterface
       */
      private $stringTranslation;

      /**
       * Sets the string translation service.
       *
       * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
       *   The translation service.
       */
      public function setStringTranslation($translation) {
        $this->stringTranslation = $translation;
      }

      /**
       * {@inheritdoc}
       */
      protected function t($string, array $args = [], array $options = []) {
        return $this->stringTranslation->translate($string, $args, $options);
      }

    };
    $this->requirementsTraitObject->setStringTranslation($translation);
  }

  /**
   * Tests that views are excluded from primary key checking.
   *
   * @covers ::getRuntimeRequirements
   */
  public function testViewsExcludedFromPrimaryKeyCheck(): void {
    // Mock the transaction isolation level query.
    $isolation_statement = $this->createMock(StatementInterface::class);
    $isolation_statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('READ-COMMITTED');

    // Mock the table type query for checking if it's a view.
    $table_type_statement = $this->createMock(StatementInterface::class);
    $table_type_statement->expects($this->exactly(2))
      ->method('fetchField')
      ->willReturnOnConsecutiveCalls('BASE TABLE', 'VIEW');

    // Mock the primary key check query.
    $primary_key_statement = $this->createMock(StatementInterface::class);
    $primary_key_statement->expects($this->once())
      ->method('fetchAllAssoc')
      ->with('Column_name')
      ->willReturn([]);

    $this->connection->expects($this->once())
      ->method('isMariaDb')
      ->willReturn(FALSE);

    // Set up query expectations.
    $this->connection->expects($this->exactly(4))
      ->method('query')
      ->willReturnCallback(function ($query, $args = []) use ($isolation_statement, $table_type_statement, $primary_key_statement) {
        if (str_contains($query, '@@SESSION.transaction_isolation')) {
          return $isolation_statement;
        }
        if (str_contains($query, 'SELECT TABLE_TYPE FROM information_schema.TABLES')) {
          return $table_type_statement;
        }
        if (str_contains($query, 'SHOW KEYS FROM')) {
          return $primary_key_statement;
        }
        throw new \Exception("Unexpected query: $query");
      });

    $this->connection->expects($this->once())
      ->method('schema')
      ->willReturn($this->schema);

    // Mock findTables to return a table and a view.
    $this->schema->expects($this->once())
      ->method('findTables')
      ->with('%')
      ->willReturn(['test_table', 'test_view']);

    $requirements = $this->requirementsTraitObject->getRuntimeRequirements($this->connection);

    // Verify that the requirement is present.
    $this->assertArrayHasKey('mysql_transaction_level', $requirements);

    // The test should pass with only the table being checked for primary key,
    // while the view is excluded. Since the table has no primary key, it should
    // be listed in the error message.
    $description = (string) $requirements['mysql_transaction_level']['description'];
    $this->assertStringContainsString('test_table', $description);
    $this->assertStringNotContainsString('test_view', $description);
  }

  /**
   * Tests that tables without primary keys are still detected.
   *
   * @covers ::getRuntimeRequirements
   */
  public function testTablesWithoutPrimaryKeyDetected(): void {
    // Mock the transaction isolation level query.
    $isolation_statement = $this->createMock(StatementInterface::class);
    $isolation_statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('READ-COMMITTED');

    // Mock the table type query - return BASE TABLE.
    $table_type_statement = $this->createMock(StatementInterface::class);
    $table_type_statement->expects($this->once())
      ->method('fetchField')
      ->willReturn('BASE TABLE');

    // Mock the primary key check query - return empty array (no primary key).
    $primary_key_statement = $this->createMock(StatementInterface::class);
    $primary_key_statement->expects($this->once())
      ->method('fetchAllAssoc')
      ->with('Column_name')
      ->willReturn([]);

    $this->connection->expects($this->once())
      ->method('isMariaDb')
      ->willReturn(FALSE);

    $this->connection->expects($this->exactly(3))
      ->method('query')
      ->willReturnCallback(function ($query, $args = []) use ($isolation_statement, $table_type_statement, $primary_key_statement) {
        if (str_contains($query, '@@SESSION.transaction_isolation')) {
          return $isolation_statement;
        }
        if (str_contains($query, 'SELECT TABLE_TYPE FROM information_schema.TABLES')) {
          return $table_type_statement;
        }
        if (str_contains($query, 'SHOW KEYS FROM')) {
          return $primary_key_statement;
        }
        throw new \Exception("Unexpected query: $query");
      });

    $this->connection->expects($this->once())
      ->method('schema')
      ->willReturn($this->schema);

    $this->schema->expects($this->once())
      ->method('findTables')
      ->with('%')
      ->willReturn(['table_without_primary_key']);

    $requirements = $this->requirementsTraitObject->getRuntimeRequirements($this->connection);

    $description = (string) $requirements['mysql_transaction_level']['description'];
    $this->assertStringContainsString('table_without_primary_key', $description);
    $this->assertStringContainsString('do not have a primary key', $description);
  }

}
