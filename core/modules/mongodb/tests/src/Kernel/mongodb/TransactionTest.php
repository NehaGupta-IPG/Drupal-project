<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel\mongodb;

use Drupal\KernelTests\Core\Database\DriverSpecificTransactionTestBase;

/**
 * Tests transaction for the MongoDB driver.
 *
 * @group Database
 */
class TransactionTest extends DriverSpecificTransactionTestBase {

  /**
   * {@inheritdoc}
   */
  public function testRollbackRootWithActiveSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRollbackRootAfterSavepointRollback(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRollbackTwiceSameSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRollbackSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRollbackSavepointWithLaterSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testCommittedTransaction(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testTransactionWithDdlStatement(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testTransactionStacking(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * Tests that transactions can continue to be used if a query fails.
   */
  public function testQueryFailureInTransaction(): void {
    $this->connection->startTransaction('test_transaction');
    $this->connection->schema()->dropTable('test');

    // Test a failed query using the query() method.
    try {
      $this->connection->query('SELECT [age] FROM {test} WHERE [name] = :name', [':name' => 'David'])->fetchField();
      $this->fail('Using the query method should have failed.');
    }
    catch (\Exception) {
      // Just continue testing.
    }

    // @todo Do more here.
  }

  /**
   * {@inheritdoc}
   */
  public function testReleaseIntermediateSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testCommitWithActiveSavepoint(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testTransactionName(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testConnectionDeprecations(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRollbackAfterDdlStatementForNonTransactionalDdlDatabase(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

  /**
   * {@inheritdoc}
   */
  public function testRootTransactionEndCallbackFailureUponDdlAndRollbackForNonTransactionalDdlDatabase(): void {
    $this->markTestSkipped('The MongoDB database driver does not support nested transactions.');
  }

}
