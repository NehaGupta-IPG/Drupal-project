<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Batch;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Batch\BatchManager;
use Drupal\Core\Batch\BatchManagerInterface;
use Drupal\Core\Batch\BatchState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the public batch API.
 */
#[CoversClass(BatchManager::class)]
#[Group('Batch')]
#[RunTestsInSeparateProcesses]
class BatchManagerTest extends KernelTestBase {

  /**
   * The batch manager.
   */
  protected BatchManagerInterface $batchManager;

  /**
   * The internal batch state, used to assert on the stored batch.
   */
  protected BatchState $batchState;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->batchManager = \Drupal::service(BatchManagerInterface::class);
    $this->batchState = \Drupal::service(BatchState::class);
  }

  /**
   * Tests that the interface is aliased to the default implementation.
   */
  public function testServiceAlias(): void {
    $this->assertInstanceOf(BatchManager::class, $this->batchManager);
  }

  /**
   * Tests adding a batch from an array and from a builder.
   */
  public function testAddBatch(): void {
    $this->batchManager->addBatch([
      'operations' => [[self::class . '::recordCallback', [1]]],
    ]);
    $this->batchManager->addBatch((new BatchBuilder())
      ->addOperation(self::class . '::recordCallback', [2]));

    $batch = $this->batchState->get();
    $this->assertCount(2, $batch['sets']);
    $this->assertSame([[self::class . '::recordCallback', [1]]], $batch['sets'][0]['operations']);
    $this->assertSame([[self::class . '::recordCallback', [2]]], $batch['sets'][1]['operations']);
  }

  /**
   * Tests that an empty batch definition does not start a batch.
   */
  public function testAddEmptyBatch(): void {
    $this->batchManager->addBatch([]);
    $this->assertFalse($this->batchManager->needsProcessing());
  }

  /**
   * Tests checking for a pending batch.
   */
  public function testNeedsProcessing(): void {
    $this->assertFalse($this->batchManager->needsProcessing());
    $this->batchManager->addBatch([
      'operations' => [[self::class . '::recordCallback', [1]]],
    ]);
    $this->assertTrue($this->batchManager->needsProcessing());
  }

  /**
   * Tests marking a batch as non-progressive.
   */
  public function testSetProgressive(): void {
    $this->batchManager->addBatch([
      'operations' => [[self::class . '::recordCallback', [1]]],
    ]);

    $this->batchManager->setProgressive(FALSE);
    $batch = $this->batchState->get();
    $this->assertFalse($batch['progressive']);

    $this->batchManager->setProgressive(TRUE);
    $batch = $this->batchState->get();
    $this->assertTrue($batch['progressive']);
  }

  /**
   * Tests that setting the progressive flag requires a batch.
   */
  public function testSetProgressiveWithoutBatch(): void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('A batch must be added before its progressive flag can be set.');
    $this->batchManager->setProgressive(FALSE);
  }

  /**
   * Tests that a non-progressive batch runs within the current request.
   */
  public function testProcessNonProgressiveBatch(): void {
    $this->batchManager->addBatch([
      'operations' => [
        [self::class . '::recordCallback', [1]],
        [self::class . '::recordCallback', [2]],
      ],
    ]);
    $this->batchManager->setProgressive(FALSE);
    $this->assertNull($this->batchManager->process());

    $this->assertSame([1, 2], \Drupal::state()->get('batch_manager_test', []));
    $this->assertFalse($this->batchManager->needsProcessing());
  }

  /**
   * Implements callback_batch_operation().
   *
   * @param int $id
   *   The id to record.
   * @param array $context
   *   The batch context.
   */
  public static function recordCallback(int $id, array &$context): void {
    $recorded = \Drupal::state()->get('batch_manager_test', []);
    $recorded[] = $id;
    \Drupal::state()->set('batch_manager_test', $recorded);
  }

}
