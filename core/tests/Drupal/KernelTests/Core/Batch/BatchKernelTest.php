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
   * Tests BatchProcessorInterface::needsUpdate().
   *
   * @see \Drupal\Core\Batch\BatchProcessorInterface::needsUpdate()
   */
  public function testNeedsUpdate(): void {
    /** @var \Drupal\Core\Batch\BatchProcessorInterface $batch_processor */
    $batch_processor = \Drupal::service(BatchProcessorInterface::class);
    // Before ever being called, the return value should be FALSE.
    $this->assertFalse($batch_processor->needsUpdate());

    // Set the value to TRUE.
    $this->assertTrue($batch_processor->needsUpdate(TRUE));
    // Check that without a parameter TRUE is returned.
    $this->assertTrue($batch_processor->needsUpdate());

    // Set the value to FALSE.
    $this->assertFalse($batch_processor->needsUpdate(FALSE));
    $this->assertFalse($batch_processor->needsUpdate());
  }

}
