<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Batch;

use Drupal\Core\Batch\BatchProcessorInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests batch functionality.
 */
#[Group('Batch')]
#[RunTestsInSeparateProcesses]
class BatchKernelTest extends KernelTestBase {

  /**
   * Tests _batch_needs_update().
   */
  public function testLegacyNeedsUpdate(): void {
    require_once $this->root . '/core/includes/batch.inc';
    // Before ever being called, the return value should be FALSE.
    $this->assertFalse(_batch_needs_update());

    // Set the value to TRUE.
    $this->assertTrue(_batch_needs_update(TRUE));
    // Check that without a parameter TRUE is returned.
    $this->assertTrue(_batch_needs_update());

    // Set the value to FALSE.
    $this->assertFalse(_batch_needs_update(FALSE));
    $this->assertFalse(_batch_needs_update());
  }

  /**
   * Tests BatchProcessor::needsUpdate().
   *
   * @see \Drupal\Core\Batch\BatchProcessor::needsUpdate()
   */
  public function testNeedsUpdate(): void {
    $batch_processor = \Drupal::service(BatchProcessorInterface::class);
    // Before ever being called, the return value should be FALSE.
    $this->assertEquals(FALSE, $batch_processor->needsUpdate());

    // Set the value to TRUE.
    $this->assertEquals(TRUE, $batch_processor->needsUpdate(TRUE));
    // Check that without a parameter TRUE is returned.
    $this->assertEquals(TRUE, $batch_processor->needsUpdate());

    // Set the value to FALSE.
    $this->assertEquals(FALSE, $batch_processor->needsUpdate(FALSE));
    $this->assertEquals(FALSE, $batch_processor->needsUpdate());
  }

}
